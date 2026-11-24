<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Data migration: copia la publicidad legacy (config_publicities) a 3
 * creatividades snapshot, una por sidebar. No toca la grilla de ficha, no
 * crea filas en ad_stats_daily/ad_clicks y es idempotente por campaign_key.
 */
class SeedAdLegacyCreatives extends Migration
{
    /**
     * Slots que reciben la creatividad legacy. product_banner_grid queda vacio.
     */
    const SLOTS = ['home_sidebar', 'product_sidebar', 'ppv_sidebar'];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $legacyKey = $this->legacyKey();
        $row = DB::table('config_publicities')->where('key', $legacyKey)->first();

        if ($row !== null) {
            $value = json_decode($row->value, true);

            if (is_array($value)
                && ! empty($value['url'])
                && ! empty($value['image'])
                && ! empty($value['name'])) {
                foreach (self::SLOTS as $slot) {
                    $this->seedCreative($legacyKey, $slot, $value);
                }
            }
        }

        $this->forgetSlots();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('ad_creatives')
            ->whereIn('campaign_key', $this->campaignKeys())
            ->delete();
    }

    /**
     * Inserta la creatividad solo si no existe ya (re-ejecutable).
     *
     * @param  string  $legacyKey
     * @param  string  $slot
     * @param  array  $value
     * @return void
     */
    private function seedCreative($legacyKey, $slot, array $value)
    {
        $placementId = DB::table('ad_placements')->where('key', $slot)->value('id');

        if ($placementId === null) {
            return;
        }

        $campaignKey = $legacyKey.'-'.$slot;

        if (DB::table('ad_creatives')->where('campaign_key', $campaignKey)->exists()) {
            return;
        }

        DB::table('ad_creatives')->insert([
            'campaign_key' => $campaignKey,
            'name' => $value['name'],
            'placement_id' => $placementId,
            'status' => 'active',
            'starts_at' => null,
            'ends_at' => null,
            'priority' => 0,
            'image_url' => $value['image'],
            'alt_text' => $value['name'],
            'headline' => $value['name'],
            'body' => null,
            'cta_label' => 'Ver más',
            'target_url' => $value['url'],
            'producto_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Key legacy origen: el default es "game" (REQ-CMP-04).
     *
     * @return string
     */
    private function legacyKey()
    {
        $aliases = (array) config('ads.legacy_aliases', []);

        if (empty($aliases)) {
            return 'game';
        }

        return (string) array_key_first($aliases);
    }

    /**
     * @return array
     */
    private function campaignKeys()
    {
        $keys = [];

        foreach (self::SLOTS as $slot) {
            $keys[] = $this->legacyKey().'-'.$slot;
        }

        return $keys;
    }

    /**
     * Descarta la seleccion cacheada de los 4 slots sembrados.
     *
     * @return void
     */
    private function forgetSlots()
    {
        foreach (array_keys((array) config('ads.placements', [])) as $key) {
            Cache::forget('ads:slot:'.$key);
        }
    }
}
