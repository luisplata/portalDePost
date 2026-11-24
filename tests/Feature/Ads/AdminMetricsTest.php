<?php

namespace Tests\Feature\Ads;

use App\AdCreative;
use App\AdPlacement;
use App\AdStatDaily;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Endpoint JSON de series diarias para las graficas del admin (REQ-MET-01..06).
 *
 * El rango del endpoint es FECHA LOCAL de config('ads.timezone') (E-1):
 * los tests fijan Carbon::setTestNow() en UTC y verifican el corte local.
 */
class AdminMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // La data migration siembra creativas legacy desde config_publicities.
        DB::table('ad_creatives')->delete();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_endpoint_requires_a_session()
    {
        $this->get('/admin/publicidad/metrics')->assertRedirect('/logout');
    }

    public function test_default_range_is_the_last_thirty_local_days()
    {
        // 02:00 UTC del 25 = 21:00 local del 24: el dia operativo es el 24.
        Carbon::setTestNow('2026-11-25 02:00:00');

        $response = $this->asAdmin()->getJson('/admin/publicidad/metrics')->assertStatus(200);

        $response->assertJsonPath('from', '2026-10-26')
            ->assertJsonPath('to', '2026-11-24');

        $series = $response->json('series');
        $this->assertCount(30, $series);
        $this->assertSame('2026-10-26', $series[0]['date']);
        $this->assertSame('2026-11-24', $series[29]['date']);
    }

    public function test_series_fills_empty_days_with_zeros_and_totals_match()
    {
        $creative = $this->makeCreative('promo-series');
        $this->seedStats($creative, '2026-11-21', 100, 5);

        $response = $this->asAdmin()
            ->getJson('/admin/publicidad/metrics?from=2026-11-20&to=2026-11-22')
            ->assertStatus(200);

        $series = $response->json('series');
        $this->assertCount(3, $series);

        // Dia sin datos: 0/0/0.0 (las graficas no pueden tener huecos).
        $this->assertSame(['date' => '2026-11-20', 'impressions' => 0, 'clicks' => 0, 'ctr' => 0.0], $series[0]);
        $this->assertSame('2026-11-21', $series[1]['date']);
        $this->assertSame(100, $series[1]['impressions']);
        $this->assertSame(5, $series[1]['clicks']);
        $this->assertSame(5.0, $series[1]['ctr']);
        $this->assertSame(0, $series[2]['impressions']);

        $response->assertJsonPath('totals.impressions', 100)
            ->assertJsonPath('totals.clicks', 5)
            ->assertJsonPath('totals.ctr', 5.0);
    }

    public function test_reversed_range_and_invalid_dates_are_rejected_with_422()
    {
        $this->asAdmin()
            ->getJson('/admin/publicidad/metrics?from=2026-05-10&to=2026-05-01')
            ->assertStatus(422)
            ->assertJson(['error' => 'invalid_range']);

        $this->asAdmin()
            ->getJson('/admin/publicidad/metrics?from=2026-02-30&to=2026-03-01')
            ->assertStatus(422)
            ->assertJson(['error' => 'invalid_range']);

        $this->asAdmin()
            ->getJson('/admin/publicidad/metrics?from=nope&to=2026-03-01')
            ->assertStatus(422)
            ->assertJson(['error' => 'invalid_range']);
    }

    public function test_ranges_longer_than_365_days_are_clamped()
    {
        $response = $this->asAdmin()
            ->getJson('/admin/publicidad/metrics?from=2020-01-01&to=2026-01-01')
            ->assertStatus(200);

        $response->assertJsonPath('from', '2025-01-02')
            ->assertJsonPath('to', '2026-01-01');

        $this->assertCount(365, $response->json('series'));
    }

    public function test_ctr_is_zero_without_impressions()
    {
        $creative = $this->makeCreative('promo-zero');
        $this->seedStats($creative, '2026-11-21', 0, 3);

        $response = $this->asAdmin()
            ->getJson('/admin/publicidad/metrics?from=2026-11-21&to=2026-11-21')
            ->assertStatus(200);

        $response->assertJsonPath('series.0.impressions', 0)
            ->assertJsonPath('series.0.clicks', 3)
            ->assertJsonPath('series.0.ctr', 0.0)
            ->assertJsonPath('totals.ctr', 0.0);
    }

    public function test_series_uses_a_single_aggregate_query()
    {
        $creative = $this->makeCreative('promo-query');
        $this->seedStats($creative, '2026-11-20', 10, 1);
        $this->seedStats($creative, '2026-11-21', 10, 1);

        $statsQueries = 0;
        DB::listen(function ($query) use (&$statsQueries) {
            if (strpos($query->sql, 'ad_stats_daily') !== false) {
                $statsQueries++;
            }
        });

        $this->asAdmin()
            ->getJson('/admin/publicidad/metrics?from=2026-11-01&to=2026-11-30')
            ->assertStatus(200);

        $this->assertSame(1, $statsQueries);
    }

    public function test_index_loads_local_chartjs_with_two_canvases()
    {
        $this->asAdmin()->get('/admin/publicidad')
            ->assertStatus(200)
            ->assertSee('vendors/Chart.js/dist/Chart.min.js', false)
            ->assertDontSee('cdnjs.cloudflare.com/ajax/libs/Chart.js', false)
            ->assertSee('ads-impressions-chart')
            ->assertSee('ads-ctr-chart');
    }

    private function asAdmin()
    {
        return $this->withSession(['admin' => (object) ['nombre' => 'tester']]);
    }

    private function seedStats(AdCreative $creative, $date, $impressions, $clicks)
    {
        AdStatDaily::create([
            'creative_id' => $creative->id,
            'placement_id' => $creative->placement_id,
            'date' => $date,
            'impressions' => $impressions,
            'clicks' => $clicks,
        ]);
    }

    private function makeCreative(string $campaignKey): AdCreative
    {
        return AdCreative::create([
            'campaign_key' => $campaignKey,
            'name' => $campaignKey,
            'placement_id' => AdPlacement::where('key', 'home_sidebar')->firstOrFail()->id,
            'status' => 'active',
            'priority' => 0,
            'image_url' => 'https://cdn.example/'.$campaignKey.'.jpg',
            'headline' => 'Headline',
            'target_url' => 'https://example.com/'.$campaignKey,
        ]);
    }
}
