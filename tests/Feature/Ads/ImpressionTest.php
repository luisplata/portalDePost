<?php

namespace Tests\Feature\Ads;

use App\AdCreative;
use App\AdPlacement;
use App\Services\AdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contrato de impresion (REQ-IMP-01..07).
 *
 * El endpoint es un beacon: 204 en toda request aceptable (tambien cuando la
 * creatividad no existe o fue deduplicada, D-04). El dia del bucket es el dia
 * local de config('ads.timezone') calculado en PHP (E-1), mientras created_at
 * sigue en UTC (D-11).
 */
class ImpressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('ad_creatives')->delete();
        Cache::flush();
        Carbon::setTestNow();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_accepted_impression_returns_204_and_increments_the_bucket()
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 24, 15, 0, 0, 'UTC'));

        $creative = $this->makeCreative('home_sidebar', ['campaign_key' => 'imp-accepted']);

        $response = $this->postImpression([
            'creative' => 'imp-accepted',
            'placement' => 'home_sidebar',
            'uuid' => 'aaaaaaaa-1111-2222-3333-444444444444',
        ]);

        $response->assertStatus(204);
        $response->assertNoContent();

        $row = $this->statsRow($creative->id, $creative->placement_id, '2026-11-24');

        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->impressions);
        $this->assertSame(0, (int) $row->clicks);
    }

    public function test_second_impression_with_the_same_uuid_is_deduplicated()
    {
        $creative = $this->makeCreative('home_sidebar', ['campaign_key' => 'imp-dedupe']);
        $uuid = 'bbbbbbbb-1111-2222-3333-444444444444';

        $payload = ['creative' => 'imp-dedupe', 'placement' => 'home_sidebar', 'uuid' => $uuid];

        $this->postImpression($payload)->assertStatus(204);
        $this->postImpression($payload)->assertStatus(204);

        $this->assertSame(1, $this->impressionsFor($creative));
        $this->assertSame(1, $this->statsRowCount($creative));
    }

    public function test_missing_uuid_falls_back_to_ip_hash_for_dedup()
    {
        $creative = $this->makeCreative('home_sidebar', ['campaign_key' => 'imp-no-uuid']);

        $payload = ['creative' => 'imp-no-uuid', 'placement' => 'home_sidebar'];

        $this->postImpression($payload)->assertStatus(204);
        $this->postImpression($payload)->assertStatus(204);

        $this->assertSame(1, $this->impressionsFor($creative));
    }

    public function test_invalid_uuid_format_falls_back_to_ip_hash()
    {
        $creative = $this->makeCreative('home_sidebar', ['campaign_key' => 'imp-bad-uuid']);

        $this->postImpression([
            'creative' => 'imp-bad-uuid',
            'placement' => 'home_sidebar',
            'uuid' => 'SHORT',
        ])->assertStatus(204);

        $this->postImpression([
            'creative' => 'imp-bad-uuid',
            'placement' => 'home_sidebar',
            'uuid' => '../../etc/passwd',
        ])->assertStatus(204);

        $this->assertSame(1, $this->impressionsFor($creative));
    }

    public function test_unknown_creative_returns_204_without_writing_rows()
    {
        $this->makeCreative('home_sidebar', ['campaign_key' => 'imp-real']);

        $response = $this->postImpression([
            'creative' => 'imp-does-not-exist',
            'placement' => 'home_sidebar',
            'uuid' => 'cccccccc-1111-2222-3333-444444444444',
        ]);

        $response->assertStatus(204);
        $this->assertSame(0, DB::table('ad_stats_daily')->count());
    }

    public function test_creative_outside_its_placement_is_not_counted()
    {
        $this->makeCreative('product_sidebar', ['campaign_key' => 'imp-wrong-slot']);

        $this->postImpression([
            'creative' => 'imp-wrong-slot',
            'placement' => 'home_sidebar',
            'uuid' => 'dddddddd-1111-2222-3333-444444444444',
        ])->assertStatus(204);

        $this->assertSame(0, DB::table('ad_stats_daily')->count());
    }

    public function test_get_method_is_not_allowed()
    {
        $this->getJson('/api/ads/impression')->assertStatus(405);
    }

    public function test_cross_origin_is_rejected_without_counting()
    {
        $creative = $this->makeCreative('home_sidebar', ['campaign_key' => 'imp-evil']);

        $response = $this->postImpression(
            ['creative' => 'imp-evil', 'placement' => 'home_sidebar', 'uuid' => 'eeeeeeee-1111-2222-3333-444444444444'],
            ['Origin' => 'https://evil.example']
        );

        $response->assertStatus(403);
        $this->assertSame(0, $this->impressionsFor($creative));
    }

    public function test_front_origin_on_another_domain_is_accepted()
    {
        // El front (onlysfree.com) y la API (admin.onlysfree.com) viven en
        // dominios distintos: el beacon tiene que pasar por config('ads.allowed_origins').
        $creative = $this->makeCreative('home_sidebar', ['campaign_key' => 'imp-front']);

        $response = $this->postImpression(
            ['creative' => 'imp-front', 'placement' => 'home_sidebar', 'uuid' => 'abababab-1111-2222-3333-444444444444'],
            ['Origin' => 'https://onlysfree.com']
        );

        $response->assertStatus(204);
        $this->assertSame(1, $this->impressionsFor($creative));
    }

    public function test_unlisted_subdomain_is_rejected()
    {
        // La lista es por host exacto: un subdominio no listado no hereda el permiso.
        $creative = $this->makeCreative('home_sidebar', ['campaign_key' => 'imp-sub']);

        $response = $this->postImpression(
            ['creative' => 'imp-sub', 'placement' => 'home_sidebar', 'uuid' => 'acacacac-1111-2222-3333-444444444444'],
            ['Origin' => 'https://evil.onlysfree.com']
        );

        $response->assertStatus(403);
        $this->assertSame(0, $this->impressionsFor($creative));
    }

    public function test_requests_without_origin_or_referer_are_rejected()
    {
        $creative = $this->makeCreative('home_sidebar', ['campaign_key' => 'imp-noorigin']);

        $response = $this->postImpression(
            ['creative' => 'imp-noorigin', 'placement' => 'home_sidebar', 'uuid' => 'ffffffff-1111-2222-3333-444444444444'],
            ['Origin' => null]
        );

        $response->assertStatus(403);
        $this->assertSame(0, $this->impressionsFor($creative));
    }

    public function test_same_host_referer_is_accepted_when_origin_is_absent()
    {
        $creative = $this->makeCreative('home_sidebar', ['campaign_key' => 'imp-referer']);

        $response = $this->postImpression(
            ['creative' => 'imp-referer', 'placement' => 'home_sidebar', 'uuid' => '99999999-1111-2222-3333-444444444444'],
            ['Origin' => null, 'Referer' => 'http://localhost/']
        );

        $response->assertStatus(204);
        $this->assertSame(1, $this->impressionsFor($creative));
    }

    public function test_empty_user_agent_is_rejected()
    {
        $creative = $this->makeCreative('home_sidebar', ['campaign_key' => 'imp-no-ua']);

        $response = $this->postImpression(
            ['creative' => 'imp-no-ua', 'placement' => 'home_sidebar', 'uuid' => '12121212-1111-2222-3333-444444444444'],
            ['User-Agent' => '']
        );

        $response->assertStatus(403);
        $this->assertSame(0, $this->impressionsFor($creative));
    }

    public function test_bot_user_agent_is_rejected()
    {
        $creative = $this->makeCreative('home_sidebar', ['campaign_key' => 'imp-bot']);

        $response = $this->postImpression(
            ['creative' => 'imp-bot', 'placement' => 'home_sidebar', 'uuid' => '13131313-1111-2222-3333-444444444444'],
            ['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)']
        );

        $response->assertStatus(403);
        $this->assertSame(0, $this->impressionsFor($creative));
    }

    public function test_missing_required_fields_return_422()
    {
        $this->postImpression(['creative' => 'imp-incomplete'])->assertStatus(422);
        $this->postImpression(['placement' => 'home_sidebar'])->assertStatus(422);

        $this->assertSame(0, DB::table('ad_stats_daily')->count());
    }

    public function test_malformed_json_body_returns_422()
    {
        $server = $this->transformHeadersToServerVars($this->validHeaders([
            'Content-Type' => 'application/json',
        ]));

        $response = $this->call('POST', '/api/ads/impression', [], [], [], $server, '{not-json');

        $response->assertStatus(422);
        $this->assertSame(0, DB::table('ad_stats_daily')->count());
    }

    public function test_rate_limit_returns_429_without_counting()
    {
        config(['ads.tracking_rate_limit' => 2]);

        $creative = $this->makeCreative('home_sidebar', ['campaign_key' => 'imp-limit']);

        $this->postImpression([
            'creative' => 'imp-limit',
            'placement' => 'home_sidebar',
            'uuid' => '14141414-1111-2222-3333-444444444444',
        ])->assertStatus(204);

        $this->postImpression([
            'creative' => 'imp-limit',
            'placement' => 'home_sidebar',
            'uuid' => '15151515-1111-2222-3333-444444444444',
        ])->assertStatus(204);

        $this->postImpression([
            'creative' => 'imp-limit',
            'placement' => 'home_sidebar',
            'uuid' => '16161616-1111-2222-3333-444444444444',
        ])->assertStatus(429);

        $this->assertSame(2, $this->impressionsFor($creative));
    }

    public function test_bucket_uses_the_local_date_not_the_utc_date()
    {
        // 2026-11-25 02:00 UTC == 2026-11-24 21:00 en America/Bogota.
        Carbon::setTestNow(Carbon::create(2026, 11, 25, 2, 0, 0, 'UTC'));

        $creative = $this->makeCreative('home_sidebar', ['campaign_key' => 'imp-local-day']);

        $this->postImpression([
            'creative' => 'imp-local-day',
            'placement' => 'home_sidebar',
            'uuid' => '17171717-1111-2222-3333-444444444444',
        ])->assertStatus(204);

        $this->assertNotNull($this->statsRow($creative->id, $creative->placement_id, '2026-11-24'));
        $this->assertNull($this->statsRow($creative->id, $creative->placement_id, '2026-11-25'));
    }

    public function test_local_midnight_boundary_splits_the_bucket()
    {
        $creative = $this->makeCreative('home_sidebar', ['campaign_key' => 'imp-boundary']);
        $uuid = '18181818-1111-2222-3333-444444444444';

        // 04:59:59 UTC == 23:59:59 local del dia 24.
        Carbon::setTestNow(Carbon::create(2026, 11, 25, 4, 59, 59, 'UTC'));
        $this->postImpression([
            'creative' => 'imp-boundary',
            'placement' => 'home_sidebar',
            'uuid' => $uuid,
        ])->assertStatus(204);

        // 05:00:00 UTC == 00:00:00 local del dia 25.
        Carbon::setTestNow(Carbon::create(2026, 11, 25, 5, 0, 0, 'UTC'));
        $this->postImpression([
            'creative' => 'imp-boundary',
            'placement' => 'home_sidebar',
            'uuid' => $uuid,
        ])->assertStatus(204);

        $this->assertSame(2, $this->statsRowCount($creative));
        $this->assertNotNull($this->statsRow($creative->id, $creative->placement_id, '2026-11-24'));
        $this->assertNotNull($this->statsRow($creative->id, $creative->placement_id, '2026-11-25'));
    }

    public function test_dedupe_resets_at_local_midnight()
    {
        $creative = $this->makeCreative('home_sidebar', ['campaign_key' => 'imp-reset']);
        $uuid = '19191919-1111-2222-3333-444444444444';

        Carbon::setTestNow(Carbon::create(2026, 11, 25, 4, 59, 0, 'UTC'));
        $this->postImpression([
            'creative' => 'imp-reset',
            'placement' => 'home_sidebar',
            'uuid' => $uuid,
        ])->assertStatus(204);

        Carbon::setTestNow(Carbon::create(2026, 11, 25, 5, 1, 0, 'UTC'));
        $this->postImpression([
            'creative' => 'imp-reset',
            'placement' => 'home_sidebar',
            'uuid' => $uuid,
        ])->assertStatus(204);

        $this->assertSame(2, $this->impressionsFor($creative));
    }

    public function test_paused_creative_after_cache_warm_is_not_counted()
    {
        $creative = $this->makeCreative('home_sidebar', ['campaign_key' => 'imp-paused']);

        // Calienta el cache de seleccion con la creatividad todavia activa.
        $this->assertCount(1, app(AdService::class)->selectForPlacement('home_sidebar'));

        $creative->status = 'paused';
        $creative->save();

        $this->postImpression([
            'creative' => 'imp-paused',
            'placement' => 'home_sidebar',
            'uuid' => '20202020-1111-2222-3333-444444444444',
        ])->assertStatus(204);

        $this->assertSame(0, $this->impressionsFor($creative));
        $this->assertSame(0, $this->statsRowCount($creative));
    }

    public function test_inactive_placement_is_not_counted()
    {
        $creative = $this->makeCreative('home_sidebar', ['campaign_key' => 'imp-inactive']);

        AdPlacement::where('key', 'home_sidebar')->update(['is_active' => 0]);

        $this->postImpression([
            'creative' => 'imp-inactive',
            'placement' => 'home_sidebar',
            'uuid' => '21212121-1111-2222-3333-444444444444',
        ])->assertStatus(204);

        $this->assertSame(0, $this->impressionsFor($creative));
    }

    public function test_upsert_reuses_a_single_row_and_increments_without_duplicates()
    {
        $creative = $this->makeCreative('home_sidebar', ['campaign_key' => 'imp-upsert']);

        $this->postImpression([
            'creative' => 'imp-upsert',
            'placement' => 'home_sidebar',
            'uuid' => '22222222-1111-2222-3333-444444444444',
        ])->assertStatus(204);

        $this->postImpression([
            'creative' => 'imp-upsert',
            'placement' => 'home_sidebar',
            'uuid' => '23232323-1111-2222-3333-444444444444',
        ])->assertStatus(204);

        $this->assertSame(1, $this->statsRowCount($creative));
        $this->assertSame(2, $this->impressionsFor($creative));
    }

    /**
     * @return array
     */
    private function validHeaders(array $overrides = [])
    {
        $headers = array_merge([
            'Origin' => 'http://localhost',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ], $overrides);

        return array_filter($headers, function ($value) {
            return $value !== null;
        });
    }

    private function postImpression(array $payload, array $headers = [])
    {
        return $this->postJson('/api/ads/impression', $payload, $this->validHeaders($headers));
    }

    private function makeCreative(string $placementKey, array $attributes = []): AdCreative
    {
        $placement = AdPlacement::where('key', $placementKey)->firstOrFail();

        return AdCreative::create(array_merge([
            'campaign_key' => 'imp-'.uniqid(),
            'name' => 'Creatividad de impresion',
            'placement_id' => $placement->id,
            'status' => 'active',
            'starts_at' => null,
            'ends_at' => null,
            'priority' => 0,
            'image_url' => 'https://cdn.example/creative.jpg',
            'alt_text' => 'Alt de prueba',
            'headline' => 'Headline de prueba',
            'body' => null,
            'cta_label' => 'Ver más',
            'target_url' => 'https://example.com/target',
            'producto_id' => null,
        ], $attributes));
    }

    private function statsRow($creativeId, $placementId, string $date)
    {
        return DB::table('ad_stats_daily')
            ->where('creative_id', $creativeId)
            ->where('placement_id', $placementId)
            ->where('date', $date)
            ->first();
    }

    private function statsRowCount(AdCreative $creative): int
    {
        return DB::table('ad_stats_daily')->where('creative_id', $creative->id)->count();
    }

    private function impressionsFor(AdCreative $creative): int
    {
        return (int) DB::table('ad_stats_daily')
            ->where('creative_id', $creative->id)
            ->sum('impressions');
    }
}
