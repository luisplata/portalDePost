<?php

namespace App\Services;

use App\AdCreative;
use App\AdPlacement;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Seleccion y presentacion de publicidad (S1a-2).
 *
 * Responsabilidades de este slice:
 *  - resolucion deterministica del slot (REQ-SEL-01),
 *  - cache por slot con invalidacion explicita (REQ-SEL-02),
 *  - superficie publica minima + click_url por request (REQ-SEL-06, REQ-API-01).
 *
 * El tracking (impresiones/clicks) llega en S1b; no vive aca todavia.
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
