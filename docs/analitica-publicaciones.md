# Analitica de publicaciones — contrato

Una "publicacion" es un producto (`productos.id`), con ficha en `/content/{slug}` y salida
monetizable en `/redirect/{id}` o `/redirection/{slug}`. La analitica es **aditiva**: no cambia
el catalogo, ni `visit_posts`, ni `log_visits`.

Panel: `/admin/analitica` (dentro del menu **Graficas** del admin).
Datos: `GET /admin/analitica/metricas` (sesion `logeado`, solo GET).

---

## 1. Metricas

| Metrica | Definicion | Formula |
|---|---|---|
| Pageviews | Renders 200 de la ficha (`/content/{slug}`, `/api/model/{id}`) | `SUM(hechos.visits)` |
| UV-d | Identidades distintas que visitaron el producto en un dia local | Contador diario `unique_visits` |
| UV-r | Identidades distintas que visitaron el producto en el rango | `COUNT(DISTINCT identity)` de identidades de visita |
| Clicks | Requests de redirect con `hotLink` no vacio | `SUM(hechos.clicks)` y eventos en `post_clicks` |
| UC-r | Identidades distintas que clickearon en el rango | `COUNT(DISTINCT identity)` de clicks |
| CV | Identidades que visitaron **y** clickearon el mismo producto | Interseccion por producto |
| Conversion principal | Visitantes unicos del rango que clickearon | `CV / UV-r * 100` (nunca > 100) |
| Conversion por pageview | Comparable con el numero legado | `clicks / visits * 100` (puede superar 100) |
| Clicks por visitante | Intensidad de click | `clicks / UV-r` |

Dia operativo: `America/Bogota` (UTC-5 fijo). El crudo se guarda en UTC y `date` es fecha local.
`config('app.timezone')` sigue en UTC y no se toca.

---

## 2. Cobertura y `null`

La respuesta declara `coverage.visits_from`, `coverage.identities_from` y `coverage.clicks_from`,
mas `uv_approximate` y `conversion_available`.

| Situacion | Respuesta |
|---|---|
| Sin datos en el rango | `warnings: ["no_data", ...]`, filas en 0 dentro de cobertura |
| Identidades no cubren el rango | `metric: "pageviews"`, `unique_visits: null`, warning `uv_unavailable` |
| Cobertura parcial de identidades | UV = suma de UV-d de los dias cubiertos, `uv_approximate: true`, warning |
| Clicks no cubiertos | `clicks: null` (nunca 0 falso), warning `clicks_unavailable` |
| Conversion sin cobertura completa | `conversion_principal: null`, `conversion_available: false` |
| Span > 365 dias | Se recorta y se emite `range_truncated` |

Regla: **si el numero no es exacto, va `null` con warning; nunca un aproximado silencioso.**

`post_stats_daily.unique_visits` es el marcador de cobertura del dia:

- `NULL: dia sin captura de identidad` (backfill historico). No aporta UV: si cae dentro
  del rango, el UV del rango queda aproximado y ese dia va `null` en la serie.
- `0` o mas: dia con captura viva. `0 es un valor real`: nadie visito ese producto ese dia.

La cobertura de identidades es **contigua**: si dentro del rango hay cualquier dia con visits y
`unique_visits` NULL (incluidos dias mixtos entre productos), `uv_approximate` pasa a `true`, ese
dia devuelve `unique_visits: null`, la conversion principal no esta disponible y se emite
`uv_approximate`. Si el rango **no tiene ningun dia cubierto**, la metrica efectiva es
`pageviews`, el UV va `null` y se emite `uv_unavailable`.

Un `click antes de la primera visita` del dia crea la fila con UV 0 y la visita posterior la
incrementa a 1, asi que el dia queda cubierto. El UV del rango se calcula de
`post_visit_identities`; un producto solo clickeado en un dia cubierto tiene UV-r 0, no `null`.
Si el rango cae antes de `identities_from`, el UV va `null` con warning `uv_unavailable`.

---

## 3. Encendido y apagado

| Perilla | Efecto |
|---|---|
| `ANALYTICS_ENABLED=false` (default) | La captura no escribe ni consulta nada; el sitio es identico al actual |
| `ANALYTICS_ENABLED=true` | Se registran visitas y clicks en las tablas nuevas |
| `ANALYTICS_TIMEZONE` | Zona del dia operativo (default `America/Bogota`) |

`config/analytics.php` centraliza `enabled`, `timezone`, `retention_days` (90), `backfill_days`
(365), `bot_ua_regex`, `drift_threshold_pct` (5), `drift_min_volume` (50) y
`opportunistic_purge` (false). Nunca `env()` fuera de `config/`.

La lectura del panel no se apaga con el flag: muestra lo ya capturado.

---

## 4. Comandos

| Comando | Que hace |
|---|---|
| `php artisan analytics:backfill-visits --from=YYYY-MM-DD --to=YYYY-MM-DD [--days=365] [--from-id=N]` | Reconstruye pageviews desde `log_visits` (UTC -> local). `unique_visits = NULL`, no pisa dias con captura viva, reejecutable |
| `php artisan analytics:purge-events [--days=90]` | Borra clicks e identidades viejos en lotes de 1000. No toca los agregados |
| `php artisan analytics:check-drift [--days=1]` | Compara pageviews de analitica contra `log_visits` en dias con captura viva. Loguea warning si desvio > 5 % y volumen >= 50 |

Schedule diario en `app/Console/Kernel.php`: purga y drift.

---

## 5. Pruebas

Docker, base `laravel_test` (el guard de `tests/TestCase.php` exige sufijo `_test`):

```bash
docker compose exec -T app ./vendor/bin/phpunit
docker compose exec -T app ./vendor/bin/phpunit tests/Feature/Analytics
```

Migraciones (solo base de test):

```bash
docker compose exec -T -e DB_DATABASE=laravel_test -e DB_USERNAME=root -e DB_PASSWORD=root app php artisan migrate
```

---

## 6. Rollback

| Pieza | Reversion |
|---|---|
| Captura | `ANALYTICS_ENABLED=false` o revert del commit |
| Tablas | `migrate:rollback --step=3` (sin dependencias externas) |
| Endpoint | Quitar la ruta `admin/analitica/metricas` |
| Panel | Revert de vista, ruta y entrada del menu Graficas |

Ninguna migracion altera tablas existentes.
