# Publicidad — contrato de API para el front

Base local: `http://localhost`

Cuatro conceptos, para que no haya ambigüedad:

| Concepto | Qué es | Dónde vive |
|---|---|---|
| **Placement** | El hueco donde va un anuncio (`home_sidebar`, `product_sidebar`, `ppv_sidebar`, `product_banner_grid`) | Tabla `ad_placements`, administrable |
| **Creative** | El anuncio en sí: imagen, headline, link, fechas, estado | Tabla `ad_creatives` |
| **Impresión** | El anuncio se vio (≥50% visible por ≥1s) | Agregado diario en `ad_stats_daily` |
| **Click** | Alguien tocó el anuncio | Fila por evento en `ad_clicks` + agregado |

**Nada de esto requiere un producto del catálogo.** Una creatividad puede apuntar a un producto (`producto_id`), pero siempre guarda su propia imagen y texto.

---

## 1. Leer un slot (público)

```
GET /api/placements/{key}
```

| Código | Cuándo |
|---|---|
| `200` | Devuelve `{placement, creatives[]}` (puede venir `creatives: []`) |
| `404` | `{"error": "placement_not_found"}` |

Respuesta real con el anuncio legacy:

```json
{
  "placement": { "key": "home_sidebar", "name": "Home sidebar", "max_items": 1 },
  "creatives": [
    {
      "campaign_key": "game-home_sidebar",
      "name": "Bellseboss Studio",
      "image_url": "https://www.bellseboss.com/assets/Graficos/BellsebossLogo.png",
      "alt_text": "Bellseboss Studio",
      "headline": "Bellseboss Studio",
      "body": null,
      "cta_label": "Ver más",
      "target_url": "https://bellseboss.itch.io/?utm_source=onlysfree.com&utm_medium=referral",
      "click_url": "http://localhost/ads/game-home_sidebar"
    }
  ]
}
```

### Campos

| Campo | Uso en el front |
|---|---|
| `campaign_key` | Identificador del anuncio. Lo necesitás para el beacon de impresión |
| `image_url` | `src` de la imagen |
| `alt_text` | `alt` (si viene vacío, usá `headline`) |
| `headline` / `body` / `cta_label` | Textos. Pueden ser `null` |
| `target_url` | Destino final informativo |
| `click_url` | **Usá este en el `href`**, no `target_url`. Es el que registra el click |

Notas:

- `creatives` ya viene recortado a `placement.max_items` y ordenado por prioridad. **No re-ordenes ni filtres.**
- El servidor cachea la selección 300 s (`config('ads.cache_ttl')`); guardar desde el admin la invalida.
- No hay campo de tamaño ni de posición: el layout lo define el front.

---

## 2. Impresión (beacon)

```
POST /api/ads/impression
Content-Type: application/json
```

```json
{ "creative": "game-home_sidebar", "placement": "home_sidebar", "uuid": "opcional" }
```

| Código | Significado |
|---|---|
| `204` | Aceptada. También si la creatividad no existe, no es servible o fue deduplicada (silencio por diseño) |
| `403` | `{"error":"forbidden"}` — origen ajeno, sin `Origin`/`Referer`, o UA vacío/bot |
| `405` | Método distinto de POST |
| `422` | `{"error":"invalid_payload"}` — falta `creative` o `placement` |
| `429` | Rate limit |

**Reglas que el front tiene que respetar:**

1. Contar solo con **≥50% del anuncio visible durante ≥1000 ms continuos** (IntersectionObserver). No dispares en `preload` ni al renderizar fuera de pantalla.
2. Mandalo con `navigator.sendBeacon` (o `fetch` con `keepalive: true`) para que no se pierda al navegar.
3. **`Origin` (o `Referer`) y un `User-Agent` real son obligatorios.** Un `curl`, `python` o cualquier UA que matchee el regex de bots recibe 403 y no cuenta.
4. Una impresión por sesión/día: si repetís el mismo `uuid`, el servidor responde 204 pero **no incrementa**.
5. Sin `uuid` el servidor usa el hash de la IP como identidad (sigue deduplicando por día).
6. El límite efectivo es **60 req/min por IP** (el throttle es compartido por todo `/api`).

`public/js/ads.js` ya implementa todo esto y lo carga el partial `ads/slot.blade.php`. Si el front es una SPA o una app móvil, replicá esa lógica; el endpoint es el mismo.

---

## 3. Click

```
GET /ads/{campaign_key}
```

| Caso | Respuesta |
|---|---|
| Creatividad servible | `302` a `target_url` + registra click |
| Existe pero está pausada/vencida/placement inactivo | `302` sin contar |
| No existe | `404` |
| Destino inválido (`//host`, `javascript:`, `data:`) | `302` a `/` |

Siempre `302` con `Cache-Control: no-store`: no lo cachees ni lo precargues.

### Compatibilidad legacy (no tocar, hay links externos)

| Ruta | Comportamiento |
|---|---|
| `GET /publicity/{key}` | Alias 302. Si está mapeado en `config('ads.legacy_aliases')` cuenta click; si no, redirige desde `config_publicities` sin contar |
| `GET /api/publicity/image/{key}` | 302 a la imagen legacy |

---

## 4. Administración (sesión, no token)

El admin es la web propia. Requiere sesión (`logeado`) y CSRF en formularios. Sin sesión redirige a `/logout`.

| Ruta | Qué hace |
|---|---|
| `GET /admin/publicidad` | Listado con impresiones, clicks y CTR de 30 días |
| `GET/POST /admin/publicidad/create` | Alta de creatividad |
| `GET/PUT/DELETE /admin/publicidad/{id}/edit` | Edición / archivar |
| `GET/PUT /admin/placements` | Nombre, `max_items` y activo por slot |
| `GET /admin/publicidad/metrics?from=Y-m-d&to=Y-m-d` | JSON para gráficas |

### `GET /admin/publicidad/metrics`

- `from`/`to` en **fecha local** (`config('ads.timezone')`, por defecto `America/Bogota`).
- Default: últimos 30 días. Rango máximo 365 (recorta `from` y devuelve las fechas efectivas).
- Fechas inválidas o `from > to` → `422`.
- `series` incluye **todos** los días, con ceros donde no hubo datos.

```json
{
  "from": "2026-11-01",
  "to": "2026-11-03",
  "series": [
    { "date": "2026-11-01", "impressions": 120, "clicks": 4, "ctr": 3.33 },
    { "date": "2026-11-02", "impressions": 0, "clicks": 0, "ctr": 0 },
    { "date": "2026-11-03", "impressions": 80, "clicks": 2, "ctr": 2.5 }
  ],
  "totals": { "impressions": 200, "clicks": 6, "ctr": 3 }
}
```

---

## 5. Cómo probarlo

### Matriz de smoke (verificada contra el stack local)

```bash
# lectura del slot
curl -s http://localhost/api/placements/home_sidebar

# 404
curl -s -o /dev/null -w "%{http_code}\n" http://localhost/api/placements/nope

# impresión sin origen -> 403
curl -s -o /dev/null -w "%{http_code}\n" -X POST http://localhost/api/ads/impression \
  -H "Content-Type: application/json" \
  -d '{"creative":"game-home_sidebar","placement":"home_sidebar"}'

# impresión válida -> 204
curl -s -o /dev/null -w "%{http_code}\n" -X POST http://localhost/api/ads/impression \
  -H "Origin: http://localhost" -H "Content-Type: application/json" \
  -H "User-Agent: Mozilla/5.0 (Windows NT 10.0) Chrome/158" \
  -d '{"creative":"game-home_sidebar","placement":"home_sidebar","uuid":"demo-0001"}'

# la misma otra vez -> 204 pero NO incrementa
curl -s -o /dev/null -w "%{http_code}\n" -X POST http://localhost/api/ads/impression \
  -H "Origin: http://localhost" -H "Content-Type: application/json" \
  -H "User-Agent: Mozilla/5.0 (Windows NT 10.0) Chrome/158" \
  -d '{"creative":"game-home_sidebar","placement":"home_sidebar","uuid":"demo-0001"}'

# click -> 302 + Location
curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" http://localhost/ads/game-home_sidebar
```

### Verificar en la base

```bash
docker compose exec -T db mysql -uroot -proot -e \
  "select creative_id, placement_id, date, impressions, clicks from laravel.ad_stats_daily;"
```

Esperado tras los comandos de arriba: `impressions = 1` (el duplicado no sumó) y `clicks = 1`.
La `date` es **fecha local**; `created_at` de `ad_clicks` está en UTC.

### Suite de tests

```bash
docker compose exec -T app php artisan config:clear
docker compose exec -T app php vendor/bin/phpunit
```

124 tests / 495 assertions. Corré siempre en Docker: el PHP del host (8.2) rompe `artisan`.

---

## 6. Configuración

Todo sale de `config/ads.php` (una perilla por cosa):

| Clave | Default | Qué controla |
|---|---|---|
| `cache_ttl` | `300` | Segundos de cache de la selección por slot |
| `tracking_rate_limit` | `120` | Definición del limiter (el techo real de `/api` es 60/min) |
| `retention_days` | `90` | Días de detalle en `ad_clicks` (los agregados no se purgan) |
| `timezone` | `env('ADS_TIMEZONE', 'America/Bogota')` | Zona del bucket diario y de las fechas del admin |
| `legacy_aliases` | `game => game-home_sidebar` | Mapeo de `/publicity/{key}` |
| `placements` | 4 keys, `max_items` 1/1/1/6 | Slots iniciales |
| `bot_ua_regex` | lista de bots | Qué UA no cuenta como impresión |

### Crear una publicidad (sin tocar código)

1. Entrá a `/admin/publicidad` → **Nueva publicidad**.
2. Elegí placement, estado `active`, fechas (se interpretan en **hora local**), prioridad y textos.
3. `campaign_key` se define al crear y **no se puede cambiar después**.
4. Guardá: el slot se invalida y el anuncio aparece en la próxima carga.

Estados: `draft` (no se sirve), `active`, `paused` (existe pero no se sirve ni cuenta click), `ended` (archivado; conserva métricas).

### Purga

```bash
docker compose exec -T app php artisan ads:purge-clicks
```

Borra clicks con más de `retention_days` y no toca `ad_stats_daily`. Está agendado a diario y además corre una vez por día de forma oportunista al abrir el listado del admin, para que funcione aunque el scheduler no exista en el hosting.

---

## 7. Notas de despliegue

- Orden: `php artisan migrate --force` → código. **No actives `config:cache` ni `route:cache`**: hoy rompen el sitio por `env()` en vistas legacy y closures en rutas (deuda aparte).
- Los pares `S1a-2`+`S1b-2` y `S2b`+`S2c` se deployan juntos: los links `/ads/*` necesitan el redirect, y el admin necesita el index.
- El producto falso actual queda en el catálogo hasta pasarlo a `estado=0`; `/content/{slug}` lo seguirá sirviendo por un bug preexistente, fuera de este cambio.
