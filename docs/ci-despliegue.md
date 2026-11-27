# CI y despliegue

Pipeline en GitHub Actions. Dos workflows y un script.

## Workflows

| Workflow | Se dispara con | Qué hace |
|---|---|---|
| `CI` | PR a `main`/`develop`, push a `main`, **manual**, o invocado por Deploy | 439 tests contra MySQL 5.7 en contenedor |
| `Deploy` | push a `develop`, push de tag `v*`, **manual** | tests + deploy a QA o a PDN |

El deploy nunca corre si los tests fallan: el job de deploy depende del de tests.

## Disparo manual

Desde la UI: **Actions → Deploy → Run workflow**, elegís entorno (`qa` o `pdn`) y la ref
(rama, tag o commit).

Equivalente por CLI:

```bash
# QA con la punta de develop
gh workflow run Deploy -f environment=qa -f ref=develop

# PDN con un tag concreto (pide aprobacion del environment production)
gh workflow run Deploy -f environment=pdn -f ref=v1.0.0

# Solo los tests, sin desplegar
gh workflow run CI
```

Para ver el estado:

```bash
gh run list --limit 5
gh run watch            # sigue el ultimo run
```

## Flujo normal

```
feature/* --PR--> develop --push--> tests + Deploy QA
develop  --PR--> main  --tag vX.Y.Z--> tests + Deploy PDN (aprobacion manual)
```

El tag **no se crea solo**: se corta a mano sobre `main` cuando la version esta lista.
Un tag es la decision de liberar a produccion; el pipeline solo ejecuta.

## Que hace bin/deploy.sh

Corre en el servidor, streameado por SSH (`ssh host 'bash -s -- <env> <sha>' < bin/deploy.sh`),
asi que siempre ejecuta la version del repo que se esta desplegando.

1. `flock` por entorno: nunca dos deploys en paralelo.
2. **Backup de la base** con `mysqldump` gzip. Si el backup falla, aborta antes de tocar codigo.
   Retiene los 5 ultimos.
3. `php artisan down`.
4. `git checkout --force <sha>`, preservando `public/.htaccess` (handler de PHP de cPanel).
5. `composer install --no-dev --optimize-autoloader` (reintenta con
   `--ignore-platform-req=ext-fileinfo` si el CLI no tiene la extension).
6. Ajusta `APP_URL`, `LOG_CHANNEL=daily`, `LOG_LEVEL` (qa: warning, pdn: error).
7. `migrate --force`.
8. `config:cache`, `route:cache`, `view:cache`.
9. `php artisan up` y **healthcheck** de `/`, `/login`, `/api/home`, `/api/packs`.
10. Si el healthcheck falla: vuelve al commit anterior, limpia caches y sale con error.

## Secrets

| Secret | Valor |
|---|---|
| `DEPLOY_KEY` | clave privada ed25519 dedicada (`github-actions-portaldepost`) |
| `SSH_HOST` | `server37.rootservers.co` |
| `SSH_PORT` | `21414` |
| `SSH_USER` | `onlysfre` |
| `SSH_KNOWN_HOSTS` | salida de `ssh-keyscan -p 21414` |

La clave publica vive en `~/.ssh/authorized_keys` del usuario de cPanel. Para revocarla:
borrar esa linea y regenerar el secret.

## Environments

`production` tiene **required reviewers**: el deploy a PDN queda esperando aprobacion
humana en la UI de GitHub.

## Limitaciones conocidas

- No hay rollback de la base automatico: el dump queda en
  `~/deploy_backups/<env>/` por si hay que restaurar a mano.
- `artisan down` no protege a quien ya tiene una request en vuelo.
- El deploy es sincronico, sin colas: si una migracion tarda, la ventana de
  mantenimiento se alarga.
