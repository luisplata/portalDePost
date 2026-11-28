#!/usr/bin/env bash
#
# Despliegue de portalDePost por entorno.
# Se ejecuta por SSH con el script streamado desde el repo:
#
#   ssh host 'bash -s -- qa <sha>' < bin/deploy.sh
#
# Hace backup de la base antes de tocar codigo, resguarda public/.htaccess
# (handler de PHP generado por cPanel), migra, cachea y verifica con
# healthcheck. Si el healthcheck falla, vuelve al commit anterior.
#
set -euo pipefail

ENV_NAME="${1:-}"
REF="${2:-}"

if [ -z "$ENV_NAME" ] || [ -z "$REF" ]; then
  echo "uso: deploy.sh <qa|pdn> <ref>"
  exit 2
fi

case "$ENV_NAME" in
  qa)
    APP=/home/onlysfre/test.laravel.onlysfree.com
    BASE=https://test.onlysfree.com
    LOG_LEVEL_VALUE=warning
    ;;
  pdn)
    APP=/home/onlysfre/admin.laravel.onlysfree.com
    BASE=https://admin.onlysfree.com
    LOG_LEVEL_VALUE=error
    ;;
  *)
    echo "ERROR: entorno desconocido: $ENV_NAME"
    exit 2
    ;;
esac

BACKUP_DIR=/home/onlysfre/deploy_backups/$ENV_NAME
LOCK=/home/onlysfre/.deploy-$ENV_NAME.lock
COMPOSER=/opt/cpanel/composer/bin/composer
mkdir -p "$BACKUP_DIR"

log() { printf '[%s] %s\n' "$(date '+%H:%M:%S')" "$*"; }

exec 9>"$LOCK"
if ! flock -n 9; then
  echo "ERROR: ya hay un despliegue en curso para $ENV_NAME"
  exit 3
fi

cd "$APP"

PREV=$(git rev-parse HEAD)
log "entorno=$ENV_NAME ref=$REF commit_actual=$PREV"

log "fetch"
if ! git fetch --prune origin --tags >/dev/null 2>&1; then
  echo "ERROR: fallo git fetch"
  exit 1
fi

if ! COMMIT=$(git rev-parse --verify --quiet "$REF^{commit}"); then
  echo "ERROR: ref inexistente: $REF"
  exit 1
fi
if [ "$COMMIT" = "$PREV" ]; then
  log "ADVERTENCIA: el commit ya estaba desplegado; se continua igual"
fi

# --- backup de la base antes de cualquier cambio ---
getval() { grep -m1 "^$1=" .env | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"; }
DBH=$(getval DB_HOST); DBN=$(getval DB_DATABASE); DBU=$(getval DB_USERNAME); DBP=$(getval DB_PASSWORD)
STAMP=$(date +%Y%m%d_%H%M%S)
DUMP="$BACKUP_DIR/db_${DBN}_${STAMP}.sql.gz"
log "backup de la base $DBN"
if mysqldump -h"$DBH" -u"$DBU" -p"$DBP" --single-transaction --quick --skip-lock-tables "$DBN" 2>/dev/null | gzip > "$DUMP"; then
  log "backup ok: $(basename "$DUMP") ($(du -h "$DUMP" | cut -f1))"
else
  echo "ERROR: fallo el backup de la base; se aborta antes de tocar codigo"
  rm -f "$DUMP"
  exit 1
fi
ls -1t "$BACKUP_DIR"/db_*.sql.gz 2>/dev/null | tail -n +6 | xargs -r rm -f

# pase lo que pase, el sitio no queda en mantenimiento
trap 'cd "$APP" 2>/dev/null && php artisan up >/dev/null 2>&1 || true' EXIT

log "modo mantenimiento"
php artisan down >/dev/null 2>&1 || true

log "actualizando codigo a $COMMIT"
cp public/.htaccess "/tmp/htaccess.$ENV_NAME.bak" 2>/dev/null || true
git checkout -- public/.htaccess 2>/dev/null || true
git -c advice.detachedHead=false checkout --force "$COMMIT" >/dev/null 2>&1
cp "/tmp/htaccess.$ENV_NAME.bak" public/.htaccess 2>/dev/null || true

log "dependencias"
if ! php -d memory_limit=512M "$COMPOSER" install --no-dev --optimize-autoloader \
      --no-interaction --no-progress >"/tmp/composer.$ENV_NAME.log" 2>&1; then
  log "composer fallo; reintento ignorando ext-fileinfo"
  php -d memory_limit=512M "$COMPOSER" install --no-dev --optimize-autoloader \
      --no-interaction --no-progress --ignore-platform-req=ext-fileinfo \
      >>"/tmp/composer.$ENV_NAME.log" 2>&1 || {
        echo "ERROR: composer install"; tail -20 "/tmp/composer.$ENV_NAME.log"; exit 1;
      }
fi
tail -2 "/tmp/composer.$ENV_NAME.log" || true

log "configuracion de .env"
cp .env .env.deploy.bak
sed -i "s|^APP_URL=.*|APP_URL=$BASE|" .env
if grep -q '^LOG_CHANNEL=' .env; then sed -i 's|^LOG_CHANNEL=.*|LOG_CHANNEL=daily|' .env; else printf '\nLOG_CHANNEL=daily' >> .env; fi
if grep -q '^LOG_LEVEL=' .env; then sed -i "s|^LOG_LEVEL=.*|LOG_LEVEL=$LOG_LEVEL_VALUE|" .env; else printf '\nLOG_LEVEL=%s' "$LOG_LEVEL_VALUE" >> .env; fi
grep -q '^LOG_DAILY_DAYS=' .env || printf '\nLOG_DAILY_DAYS=14' >> .env
[ -n "$(tail -c1 .env)" ] && printf '\n' >> .env

log "migraciones"
php artisan migrate --force --no-interaction

log "caches"
php artisan config:cache >/dev/null
php artisan route:cache  >/dev/null
php artisan view:cache   >/dev/null

log "saliendo de mantenimiento"
php artisan up >/dev/null 2>&1 || true

log "healthcheck"
FAIL=0
for path in / /login /api/home /api/packs; do
  CODE=$(curl -s -o /dev/null -m 20 -w '%{http_code}' "$BASE$path" || echo 000)
  printf '   %-12s %s\n' "$path" "$CODE"
  [ "$CODE" = "200" ] || FAIL=1
done

if [ "$FAIL" -ne 0 ]; then
  log "HEALTHCHECK FALLIDO -> rollback a $PREV"
  php artisan down >/dev/null 2>&1 || true
  git -c advice.detachedHead=false checkout --force "$PREV" >/dev/null 2>&1 || true
  php -d memory_limit=512M "$COMPOSER" install --no-dev --optimize-autoloader \
      --no-interaction --no-progress --ignore-platform-req=ext-fileinfo >/dev/null 2>&1 || true
  php artisan config:clear >/dev/null 2>&1 || true
  php artisan route:clear  >/dev/null 2>&1 || true
  php artisan view:clear   >/dev/null 2>&1 || true
  php artisan up >/dev/null 2>&1 || true
  echo "ROLLBACK COMPLETADO a $PREV"
  exit 1
fi

echo "$COMMIT" > "$BACKUP_DIR/last_success.txt"
echo "$PREV"   > "$BACKUP_DIR/last_previous.txt"
log "deploy ok: $COMMIT en $BASE"
