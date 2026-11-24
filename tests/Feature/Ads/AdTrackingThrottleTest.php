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
 * Rate limit dedicado al tracking (REQ-IMP-04).
 *
 * El limiter `ads-tracking` se registra en RouteServiceProvider::boot() y lee
 * config('ads.tracking_rate_limit') en cada request. El bucket es por IP y
 * COMPARTIDO con /api/ads/impression, por eso el test limpia el limiter y
 * restaura el config al terminar: el estado no debe filtrarse a ClickTest ni
 * a ImpressionTest.
 */
class AdTrackingThrottleTest extends TestCase
{
    use RefreshDatabase;

    /** @var mixed */
    private $originalLimit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalLimit = config('ads.tracking_rate_limit');

        DB::table('ad_creatives')->delete();
        Cache::flush();
        Carbon::setTestNow(Carbon::create(2026, 11, 24, 15, 0, 0, 'UTC'));
    }

    protected function tearDown(): void
    {
        // El bucket de rate limit vive en cache: se vacia para no contaminar
        // los tests de click/impresion que corren despues en el mismo proceso.
        Cache::flush();
        config(['ads.tracking_rate_limit' => $this->originalLimit]);
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_ads_routes_return_429_over_the_limit_without_counting_the_rejected_click()
    {
        config(['ads.tracking_rate_limit' => 2]);

        $this->makeCreative('home_sidebar', 'game-home_sidebar');

        // 2 requests permitidas; la 3a excede el bucket por IP.
        $this->get('/ads/game-home_sidebar')->assertStatus(302);
        $this->get('/ads/game-home_sidebar')->assertStatus(302);
        $this->get('/ads/game-home_sidebar')->assertStatus(429);

        // El 429 corta en el middleware: no se registra el click rechazado.
        $this->assertSame(2, DB::table('ad_clicks')->count());

        // El alias /publicity/{key} comparte limiter: se limpia para ejercitarlo aparte.
        Cache::flush();

        $this->get('/publicity/game')->assertStatus(302);
        $this->get('/publicity/game')->assertStatus(302);
        $this->get('/publicity/game')->assertStatus(429);

        $this->assertSame(4, DB::table('ad_clicks')->count());
    }

    private function makeCreative(string $placementKey, string $campaignKey): AdCreative
    {
        return AdCreative::create([
            'campaign_key' => $campaignKey,
            'name' => $campaignKey,
            'placement_id' => AdPlacement::where('key', $placementKey)->firstOrFail()->id,
            'status' => 'active',
            'priority' => 0,
            'image_url' => 'https://cdn.example/'.$campaignKey.'.jpg',
            'headline' => 'Headline',
            'target_url' => 'https://example.com/'.$campaignKey,
        ]);
    }
}
