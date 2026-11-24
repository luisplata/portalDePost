<?php

namespace App\Services;

use App\AdClick;
use App\AdCreative;
use App\AdPlacement;
use App\Producto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seleccion, presentacion y tracking de publicidad.
 *
 * Responsabilidades de este slice:
 *  - resolucion deterministica del slot (REQ-SEL-01),
 *  - cache por slot con invalidacion explicita (REQ-SEL-02),
 *  - superficie publica minima + click_url por request (REQ-SEL-06, REQ-API-01),
 *  - impresiones: servibilidad, dedupe diario y upsert atomico (S1b-1),
 *  - clicks: detalle + upsert atomico sin dedupe y destino seguro (S1b-2).
 *
 * El dia operativo de las metricas es el dia LOCAL de config('ads.timezone');
 * el almacenamiento crudo (created_at) sigue en UTC (E-1 / D-11).
 */
class AdService
{
    /**
     * Campos publicos del anuncio que se cachean y se exponen.
     * `click_url` NO se cachea: depende del host de cada request.
     */
    const PUBLIC_FIELDS = [
        'campaign_key',
        'name',
        'image_url',
        'alt_text',
        'headline',
        'body',
        'cta_label',
        'target_url',
    ];

    /**
     * Devuelve las creatividades servibles de un slot.
     *
     * 2 queries en miss, 0 en hit. Cualquier error se degrada a [] porque la
     * publicidad nunca debe romper la pagina (REQ-PLC-03).
     *
     * @param  string  $key
     * @return array
     */
    public function selectForPlacement($key)
    {
        $cacheKey = $this->slotCacheKey($key);

        try {
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }

            $placement = AdPlacement::query()
                ->where('key', $key)
                ->where('is_active', 1)
                ->first();

            if ($placement === null) {
                Cache::put($cacheKey, [], $this->cacheTtl());

                return [];
            }

            $now = now();

            $creatives = AdCreative::query()
                ->where('placement_id', $placement->id)
                ->where('status', 'active')
                ->where(function ($query) use ($now) {
                    $query->whereNull('starts_at')
                        ->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($query) use ($now) {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', $now);
                })
                ->orderBy('priority', 'desc')
                ->orderBy('id', 'asc')
                ->limit($placement->max_items)
                ->get(self::PUBLIC_FIELDS)
                ->map(function ($creative) {
                    return $this->toPublicArray($creative);
                })
                ->all();

            Cache::put($cacheKey, $creatives, $this->cacheTtl());

            return $creatives;
        } catch (\Throwable $e) {
            Log::error('ads: no se pudo resolver el slot', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Invalida la seleccion cacheada de un slot.
     *
     * @param  string  $key
     * @return void
     */
    public function forgetSlot($key)
    {
        Cache::forget($this->slotCacheKey($key));
    }

    /**
     * Agrega `click_url` absoluta (dependiente del host de la request).
     *
     * @param  array  $creatives
     * @return array
     */
    public function presentPublic(array $creatives)
    {
        return array_map(function ($creative) {
            $creative['click_url'] = url('/ads/'.$creative['campaign_key']);

            return $creative;
        }, $creatives);
    }

    /**
     * Fecha local (config('ads.timezone')) del dia operativo.
     *
     * MySQL nunca calcula el dia: se resuelve en PHP para que el resultado sea
     * deterministico y testeable con Carbon::setTestNow() (E-1).
     *
     * @return string  Y-m-d
     */
    public function today()
    {
        return Carbon::now($this->timezone())->toDateString();
    }

    /**
     * Hash estable de la IP para privacidad y fallback de dedupe (D-05).
     *
     * @param  string|null  $ip
     * @return string  64 hex en minuscula
     */
    public function ipHash($ip)
    {
        return hash('sha256', (string) $ip.'|'.(string) config('app.key'));
    }

    /**
     * @param  string|null  $userAgent
     * @return string
     */
    public function truncateUserAgent($userAgent)
    {
        return substr((string) $userAgent, 0, 255);
    }

    /**
     * @param  string|null  $referer
     * @return string
     */
    public function truncateReferer($referer)
    {
        return substr((string) $referer, 0, 255);
    }

    /**
     * Reevalua en el servidor si la creatividad es servible en este instante.
     *
     * Una sola query: status active + placement activo + ventana vigente.
     * Devuelve el par (creative_id, placement_id) o null.
     *
     * @param  string  $campaignKey
     * @param  string  $placementKey
     * @return object|null
     */
    public function isServable($campaignKey, $placementKey)
    {
        $now = now();

        try {
            return AdCreative::query()
                ->join('ad_placements', 'ad_placements.id', '=', 'ad_creatives.placement_id')
                ->where('ad_creatives.campaign_key', $campaignKey)
                ->where('ad_placements.key', $placementKey)
                ->where('ad_placements.is_active', 1)
                ->where('ad_creatives.status', 'active')
                ->where(function ($query) use ($now) {
                    $query->whereNull('ad_creatives.starts_at')
                        ->orWhere('ad_creatives.starts_at', '<=', $now);
                })
                ->where(function ($query) use ($now) {
                    $query->whereNull('ad_creatives.ends_at')
                        ->orWhere('ad_creatives.ends_at', '>=', $now);
                })
                ->select(
                    'ad_creatives.id as creative_id',
                    'ad_creatives.placement_id as placement_id'
                )
                ->first();
        } catch (\Throwable $e) {
            Log::error('ads: no se pudo evaluar la servibilidad', [
                'campaign_key' => $campaignKey,
                'placement_key' => $placementKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Registra una impresion con dedupe por (creative, placement, identidad, dia local).
     *
     * La identidad es el uuid valido recibido o, si falta o es invalido,
     * `ip:` + ip_hash (D-05). El upsert es un unico statement atomico compatible
     * con MySQL 5.7; la fecha se calcula en PHP (E-1).
     *
     * @param  int|string  $creativeId
     * @param  int|string  $placementId
     * @param  string|null  $uuid
     * @param  string|null  $ip
     * @return bool  true si conto, false si fue deduplicada
     */
    public function recordImpression($creativeId, $placementId, $uuid = null, $ip = null)
    {
        $identity = (is_string($uuid) && preg_match('/^[a-z0-9-]{8,64}$/', $uuid) === 1)
            ? $uuid
            : 'ip:'.$this->ipHash($ip);

        $date = $this->today();
        $cacheKey = 'ads:imp:'.$creativeId.':'.$placementId.':'.$identity.':'.$date;

        $localNow = Carbon::now($this->timezone());
        $expires = $localNow->copy()->endOfDay()->addHour();
        $ttl = max(60, $expires->getTimestamp() - $localNow->getTimestamp());

        if (! Cache::add($cacheKey, 1, $ttl)) {
            return false;
        }

        $now = now();

        DB::statement(
            'INSERT INTO ad_stats_daily (creative_id, placement_id, `date`, impressions, clicks, created_at, updated_at) '
            .'VALUES (?, ?, ?, 1, 0, ?, ?) '
            .'ON DUPLICATE KEY UPDATE impressions = impressions + 1, updated_at = VALUES(updated_at)',
            [
                (int) $creativeId,
                (int) $placementId,
                $date,
                $now->toDateTimeString(),
                $now->toDateTimeString(),
            ]
        );

        return true;
    }

    /**
     * Registra un click: detalle en ad_clicks (UTC) + incremento del bucket
     * diario local en la misma transaccion.
     *
     * A diferencia de la impresion NO hay dedupe: cada click es un evento
     * (REQ-CLK-01..03). El uuid se guarda solo si cumple el formato esperado.
     *
     * @param  int|string  $creativeId
     * @param  int|string  $placementId
     * @param  string|null  $uuid
     * @param  string|null  $ip
     * @param  string|null  $referer
     * @param  string|null  $userAgent
     * @return void
     */
    public function recordClick(
        $creativeId,
        $placementId,
        $uuid = null,
        $ip = null,
        $referer = null,
        $userAgent = null
    ) {
        $validUuid = (is_string($uuid) && preg_match('/^[a-z0-9-]{8,64}$/', $uuid) === 1)
            ? $uuid
            : null;

        $referer = $this->truncateReferer($referer);
        $userAgent = $this->truncateUserAgent($userAgent);

        $date = $this->today();
        $now = now()->utc();
        $nowString = $now->toDateTimeString();

        DB::transaction(function () use (
            $creativeId,
            $placementId,
            $validUuid,
            $referer,
            $userAgent,
            $ip,
            $date,
            $now,
            $nowString
        ) {
            $click = new AdClick();
            $click->creative_id = (int) $creativeId;
            $click->placement_id = (int) $placementId;
            $click->anonymous_user_uuid = $validUuid;
            $click->referer = $referer === '' ? null : $referer;
            $click->ip_hash = $this->ipHash($ip);
            $click->user_agent = $userAgent === '' ? null : $userAgent;
            $click->created_at = $now;
            $click->save();

            DB::statement(
                'INSERT INTO ad_stats_daily (creative_id, placement_id, `date`, impressions, clicks, created_at, updated_at) '
                .'VALUES (?, ?, ?, 0, 1, ?, ?) '
                .'ON DUPLICATE KEY UPDATE clicks = clicks + 1, updated_at = VALUES(updated_at)',
                [
                    (int) $creativeId,
                    (int) $placementId,
                    $date,
                    $nowString,
                    $nowString,
                ]
            );
        });
    }

    /**
     * Prellena campos vacios del formulario desde el producto elegido
     * (REQ-CRT-04). Solo se usa en `create`; una vez guardada la creatividad
     * el snapshot manda y el producto no se vuelve a leer.
     *
     * @param  array  $data
     * @return array
     */
    public function prefillFromProducto(array $data)
    {
        if (empty($data['producto_id'])) {
            return $data;
        }

        $producto = Producto::find($data['producto_id']);

        if ($producto === null) {
            return $data;
        }

        if (empty($data['target_url'])) {
            $data['target_url'] = '/content/'.$producto->ConvertNameNormalToUrl();
        }

        if (empty($data['headline'])) {
            $data['headline'] = $producto->nombre;
        }

        if (empty($data['image_url'])) {
            $data['image_url'] = $producto->imagen;
        }

        return $data;
    }

    /**
     * Destino seguro para un click: http(s) absoluta o ruta interna.
     *
     * Cualquier otro valor (javascript:, //host, vacio, corrupto) cae a "/"
     * como defensa en profundidad (REQ-CLK-01 S2).
     *
     * @param  string|null  $targetUrl
     * @return string
     */
    public function safeTargetUrl($targetUrl)
    {
        $target = (string) $targetUrl;

        if (preg_match('#^https?://#i', $target) === 1) {
            return $target;
        }

        if (preg_match('#^/(?!/)#', $target) === 1) {
            return $target;
        }

        return '/';
    }

    /**
     * @return string
     */
    private function timezone()
    {
        return (string) config('ads.timezone', 'America/Bogota');
    }

    /**
     * @param  string  $key
     * @return string
     */
    private function slotCacheKey($key)
    {
        return 'ads:slot:'.$key;
    }

    /**
     * @return int
     */
    private function cacheTtl()
    {
        return (int) config('ads.cache_ttl', 300);
    }

    /**
     * @param  \App\AdCreative  $creative
     * @return array
     */
    private function toPublicArray(AdCreative $creative)
    {
        return [
            'campaign_key' => $creative->campaign_key,
            'name' => $creative->name,
            'image_url' => $creative->image_url,
            'alt_text' => $creative->alt_text,
            'headline' => $creative->headline,
            'body' => $creative->body,
            'cta_label' => $creative->cta_label,
            'target_url' => $creative->target_url,
        ];
    }
}
