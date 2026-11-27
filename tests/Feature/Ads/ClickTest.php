<?php

namespace Tests\Feature\Ads;

use App\AdCreative;
use App\AdPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contrato de click y aliases legacy (REQ-CLK-01..05, REQ-PRV-01).
 *
 * /ads/{campaign_key} registra un click por evento (sin dedupe) y redirige 302
 * con Cache-Control no-store. Una creatividad existente pero no servible
 * conserva el enlace pero no cuenta (D-06). El bucket diario usa la fecha
 * LOCAL (E-1) y created_at queda en UTC (D-11).
 */
class ClickTest extends TestCase
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

    public function test_servable_click_returns_302_with_no_store_and_records_the_click()
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 24, 15, 0, 0, 'UTC'));

        $creative = $this->makeCreative('home_sidebar', [
            'campaign_key' => 'click-ok',
            'target_url' => 'https://example.com/promo',
        ]);

        $uuid = 'aaaaaaaa-1111-2222-3333-444444444444';

        $response = $this->withCookie('anonymous_user_id', $uuid)
            ->withHeader('Referer', 'https://portal.test/content/ficha')
            ->get('/ads/click-ok');

        $response->assertStatus(302);
        $this->assertSame('https://example.com/promo', $response->headers->get('Location'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->assertSame(1, DB::table('ad_clicks')->count());

        $row = DB::table('ad_clicks')->first();

        $this->assertSame((int) $creative->id, (int) $row->creative_id);
        $this->assertSame((int) $creative->placement_id, (int) $row->placement_id);
        $this->assertSame($uuid, $row->anonymous_user_uuid);
        $this->assertSame('https://portal.test/content/ficha', $row->referer);
        $this->assertSame(
            hash('sha256', '127.0.0.1|'.(string) config('app.key')),
            $row->ip_hash
        );
        $this->assertSame(64, strlen((string) $row->ip_hash));

        $stats = $this->statsRow($creative, '2026-11-24');

        $this->assertNotNull($stats);
        $this->assertSame(1, (int) $stats->clicks);
        $this->assertSame(0, (int) $stats->impressions);
    }

    public function test_route_accepts_legacy_underscored_campaign_keys()
    {
        $creative = $this->makeCreative('home_sidebar', [
            'campaign_key' => 'game-home_sidebar',
            'target_url' => 'https://example.com/legacy-underscore',
        ]);

        $response = $this->get('/ads/game-home_sidebar');

        $response->assertStatus(302);
        $this->assertSame('https://example.com/legacy-underscore', $response->headers->get('Location'));
        $this->assertSame(1, $this->clicksFor($creative));
    }

    public function test_each_click_is_an_event_without_dedupe()
    {
        $creative = $this->makeCreative('home_sidebar', ['campaign_key' => 'click-repeat']);

        $uuid = 'bbbbbbbb-1111-2222-3333-444444444444';

        $this->withCookie('anonymous_user_id', $uuid)->get('/ads/click-repeat')->assertStatus(302);
        $this->withCookie('anonymous_user_id', $uuid)->get('/ads/click-repeat')->assertStatus(302);

        $this->assertSame(2, DB::table('ad_clicks')->count());
        $this->assertSame(1, $this->statsRowCount($creative));
        $this->assertSame(2, $this->clicksFor($creative));
    }

    public function test_paused_creative_redirects_without_counting()
    {
        $this->makeCreative('home_sidebar', [
            'campaign_key' => 'click-paused',
            'status' => 'paused',
            'target_url' => 'https://example.com/paused',
        ]);

        $response = $this->get('/ads/click-paused');

        $response->assertStatus(302);
        $this->assertSame('https://example.com/paused', $response->headers->get('Location'));
        $this->assertSame(0, DB::table('ad_clicks')->count());
        $this->assertSame(0, DB::table('ad_stats_daily')->count());
    }

    public function test_creative_ended_by_date_redirects_without_counting()
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 24, 15, 0, 0, 'UTC'));

        $this->makeCreative('home_sidebar', [
            'campaign_key' => 'click-ended',
            'status' => 'active',
            'ends_at' => Carbon::create(2026, 11, 23, 23, 59, 59, 'UTC'),
            'target_url' => 'https://example.com/ended',
        ]);

        $response = $this->get('/ads/click-ended');

        $response->assertStatus(302);
        $this->assertSame('https://example.com/ended', $response->headers->get('Location'));
        $this->assertSame(0, DB::table('ad_clicks')->count());
        $this->assertSame(0, DB::table('ad_stats_daily')->count());
    }

    public function test_inactive_placement_redirects_without_counting()
    {
        $this->makeCreative('home_sidebar', [
            'campaign_key' => 'click-inactive-slot',
            'target_url' => 'https://example.com/inactive',
        ]);

        AdPlacement::where('key', 'home_sidebar')->update(['is_active' => 0]);

        $response = $this->get('/ads/click-inactive-slot');

        $response->assertStatus(302);
        $this->assertSame('https://example.com/inactive', $response->headers->get('Location'));
        $this->assertSame(0, DB::table('ad_clicks')->count());
        $this->assertSame(0, DB::table('ad_stats_daily')->count());
    }

    public function test_unknown_campaign_key_returns_404()
    {
        $response = $this->get('/ads/no-existe');

        $response->assertStatus(404);
        $this->assertSame(0, DB::table('ad_clicks')->count());
        $this->assertSame(0, DB::table('ad_stats_daily')->count());
    }

    public function test_malformed_target_url_falls_back_to_root_and_still_counts()
    {
        $creative = $this->makeCreative('home_sidebar', [
            'campaign_key' => 'click-bad-target',
            'target_url' => 'javascript:alert(1)',
        ]);

        $response = $this->get('/ads/click-bad-target');

        $response->assertStatus(302);
        $this->assertSame('/', $response->headers->get('Location'));
        $this->assertSame(1, DB::table('ad_clicks')->count());
        $this->assertSame(1, $this->clicksFor($creative));
    }

    public function test_empty_target_url_falls_back_to_root_and_still_counts()
    {
        $creative = $this->makeCreative('home_sidebar', [
            'campaign_key' => 'click-empty-target',
            'target_url' => '',
        ]);

        $response = $this->get('/ads/click-empty-target');

        $response->assertStatus(302);
        $this->assertSame('/', $response->headers->get('Location'));
        $this->assertSame(1, DB::table('ad_clicks')->count());
        $this->assertSame(1, $this->clicksFor($creative));
    }

    public function test_internal_target_url_is_preserved()
    {
        $this->makeCreative('home_sidebar', [
            'campaign_key' => 'click-internal',
            'target_url' => '/content/ficha',
        ]);

        $response = $this->get('/ads/click-internal');

        $response->assertStatus(302);
        $this->assertSame('/content/ficha', $response->headers->get('Location'));
    }

    public function test_click_without_cookie_stores_null_uuid_and_still_counts()
    {
        $creative = $this->makeCreative('home_sidebar', ['campaign_key' => 'click-no-cookie']);

        $this->get('/ads/click-no-cookie')->assertStatus(302);

        $row = DB::table('ad_clicks')->first();

        $this->assertNotNull($row);
        $this->assertNull($row->anonymous_user_uuid);
        $this->assertSame(1, $this->clicksFor($creative));
    }

    public function test_invalid_cookie_uuid_is_not_persisted()
    {
        $this->makeCreative('home_sidebar', ['campaign_key' => 'click-bad-uuid']);

        $this->withCookie('anonymous_user_id', 'not a valid uuid!!')
            ->get('/ads/click-bad-uuid')
            ->assertStatus(302);

        $this->assertNull(DB::table('ad_clicks')->first()->anonymous_user_uuid);
    }

    public function test_ip_hash_is_stable_across_clicks_and_never_stores_the_raw_ip()
    {
        $this->makeCreative('home_sidebar', ['campaign_key' => 'click-hash']);

        $uuid = 'cccccccc-1111-2222-3333-444444444444';

        $this->withCookie('anonymous_user_id', $uuid)->get('/ads/click-hash');
        $this->withCookie('anonymous_user_id', $uuid)->get('/ads/click-hash');

        $rows = DB::table('ad_clicks')->get();

        $this->assertCount(2, $rows);
        $this->assertSame($rows[0]->ip_hash, $rows[1]->ip_hash);
        $this->assertSame(64, strlen((string) $rows[0]->ip_hash));
        $this->assertNotSame('127.0.0.1', $rows[0]->ip_hash);
    }

    public function test_referer_and_user_agent_are_truncated_to_255()
    {
        $this->makeCreative('home_sidebar', ['campaign_key' => 'click-truncate']);

        $this->withHeader('Referer', str_repeat('r', 300))
            ->withHeader('User-Agent', str_repeat('u', 700))
            ->get('/ads/click-truncate')
            ->assertStatus(302);

        $row = DB::table('ad_clicks')->first();

        $this->assertSame(255, strlen((string) $row->referer));
        $this->assertSame(255, strlen((string) $row->user_agent));
    }

    public function test_created_at_is_utc_while_the_stats_bucket_is_the_local_day()
    {
        // 2026-11-25 02:00 UTC == 2026-11-24 21:00 en America/Bogota.
        Carbon::setTestNow(Carbon::create(2026, 11, 25, 2, 0, 0, 'UTC'));

        $creative = $this->makeCreative('home_sidebar', ['campaign_key' => 'click-local-day']);

        $this->get('/ads/click-local-day')->assertStatus(302);

        $row = DB::table('ad_clicks')->first();

        $this->assertSame('2026-11-25 02:00:00', (string) $row->created_at);

        $this->assertNotNull($this->statsRow($creative, '2026-11-24'));
        $this->assertNull($this->statsRow($creative, '2026-11-25'));
    }

    public function test_alias_with_mapping_behaves_like_ads_and_counts_the_click()
    {
        $creative = $this->makeCreative('home_sidebar', [
            'campaign_key' => 'game-home_sidebar',
            'target_url' => 'https://example.com/game',
        ]);

        $response = $this->get('/publicity/game');

        $response->assertStatus(302);
        $this->assertSame('https://example.com/game', $response->headers->get('Location'));
        $this->assertSame(1, DB::table('ad_clicks')->count());
        $this->assertSame(1, $this->clicksFor($creative));
    }

    public function test_alias_without_mapping_falls_back_to_config_publicities_without_counting()
    {
        DB::table('config_publicities')->insert([
            'key' => 'legacy-otro',
            'value' => '{"url":"https://legacy.example/x","image":"https://legacy.example/x.png","name":"Legacy"}',
        ]);

        $response = $this->get('/publicity/legacy-otro');

        $response->assertStatus(302);
        $this->assertSame('https://legacy.example/x', $response->headers->get('Location'));
        $this->assertSame(0, DB::table('ad_clicks')->count());
        $this->assertSame(0, DB::table('ad_stats_daily')->count());
    }

    public function test_publicity_without_alias_or_legacy_row_returns_404()
    {
        $response = $this->get('/publicity/nada-que-ver');

        $response->assertStatus(404);
        $this->assertSame(0, DB::table('ad_clicks')->count());
    }

    public function test_mapped_alias_with_missing_creative_returns_404()
    {
        DB::table('ad_creatives')->where('campaign_key', 'game-home_sidebar')->delete();

        $this->get('/publicity/game')->assertStatus(404);
    }

    public function test_image_alias_redirects_to_the_creative_image()
    {
        $this->makeCreative('home_sidebar', [
            'campaign_key' => 'game-home_sidebar',
            'image_url' => 'https://cdn.example/creative.jpg',
        ]);

        $response = $this->get('/api/publicity/image/game');

        $response->assertStatus(302);
        $this->assertSame('https://cdn.example/creative.jpg', $response->headers->get('Location'));
    }

    public function test_image_without_creative_falls_back_to_the_legacy_config_publicity()
    {
        // Sin creatividad mapeada: se conserva ConfigPublicity::GetImage ('game').
        $response = $this->get('/api/publicity/image/game');

        $response->assertStatus(302);
        $this->assertStringContainsString(
            'BellsebossLogo.png',
            (string) $response->headers->get('Location')
        );
    }

    public function test_image_for_unknown_key_returns_404()
    {
        $response = $this->get('/api/publicity/image/nada-que-ver');

        $response->assertStatus(404);
        $response->assertJson(['error' => 'Publicity not found']);
    }

    private function makeCreative(string $placementKey, array $attributes = []): AdCreative
    {
        $placement = AdPlacement::where('key', $placementKey)->firstOrFail();

        return AdCreative::create(array_merge([
            'campaign_key' => 'click-'.uniqid(),
            'name' => 'Creatividad de click',
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

    private function statsRow(AdCreative $creative, string $date)
    {
        return DB::table('ad_stats_daily')
            ->where('creative_id', $creative->id)
            ->where('placement_id', $creative->placement_id)
            ->where('date', $date)
            ->first();
    }

    private function statsRowCount(AdCreative $creative): int
    {
        return DB::table('ad_stats_daily')->where('creative_id', $creative->id)->count();
    }

    private function clicksFor(AdCreative $creative): int
    {
        return (int) DB::table('ad_stats_daily')
            ->where('creative_id', $creative->id)
            ->sum('clicks');
    }
}
