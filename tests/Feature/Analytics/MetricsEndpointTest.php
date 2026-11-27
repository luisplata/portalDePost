<?php

namespace Tests\Feature\Analytics;

use App\Services\AnalyticsTimeService;
use App\Services\PostMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contrato del endpoint de metricas (T-S3a-04..07, REQ-S3-01..07, S4-07).
 *
 * Solo lectura, middleware `logeado`, 6 sentencias constantes y degradacion
 * honesta: null + warning cuando la cobertura no alcanza.
 */
class MetricsEndpointTest extends AnalyticsTestSupport
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_endpoint_requires_a_session()
    {
        $this->getJson('/admin/analitica/metricas')->assertRedirect('/logout');
    }

    public function test_other_methods_are_not_allowed()
    {
        $this->asAdmin()->post('/admin/analitica/metricas')->assertStatus(405);
    }

    public function test_default_range_is_the_last_thirty_local_days()
    {
        Carbon::setTestNow('2026-11-25 02:00:00');

        $response = $this->asAdmin()->getJson('/admin/analitica/metricas')->assertStatus(200);

        $response->assertJsonPath('from', '2026-10-26')
            ->assertJsonPath('to', '2026-11-24')
            ->assertJsonPath('timezone', 'America/Bogota');

        $series = $response->json('series');

        $this->assertCount(30, $series);
        $this->assertSame('2026-10-26', $series[0]['date']);
        $this->assertSame('2026-11-24', $series[29]['date']);
    }

    public function test_invalid_ranges_return_422()
    {
        foreach ([
            ['from' => '2026-05-10', 'to' => '2026-05-01'],
            ['from' => '2026-13-01', 'to' => '2026-11-24'],
            ['from' => '2026-02-30', 'to' => '2026-11-24'],
            ['from' => 'not-a-date', 'to' => '2026-11-24'],
        ] as $query) {
            $this->asAdmin()
                ->getJson('/admin/analitica/metricas?'.http_build_query($query))
                ->assertStatus(422)
                ->assertJson(['error' => 'invalid_range']);
        }
    }

    public function test_an_unknown_metric_returns_422()
    {
        $this->asAdmin()
            ->getJson('/admin/analitica/metricas?metric=clicks')
            ->assertStatus(422)
            ->assertJson(['error' => 'invalid_metric']);
    }

    public function test_a_span_longer_than_365_days_is_truncated_with_a_warning()
    {
        $response = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2024-01-01&to=2026-01-01')
            ->assertStatus(200);

        $response->assertJsonPath('from', '2025-01-02')
            ->assertJsonPath('to', '2026-01-01');

        $this->assertContains('range_truncated', $response->json('warnings'));
    }

    public function test_empty_tables_declare_null_coverage_and_no_invented_numbers()
    {
        $productoId = $this->makeProduct(['nombre' => 'Sin Datos']);

        $response = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-10-26&to=2026-11-24')
            ->assertStatus(200);

        $response->assertJsonPath('coverage.visits_from', null)
            ->assertJsonPath('coverage.identities_from', null)
            ->assertJsonPath('coverage.clicks_from', null)
            ->assertJsonPath('metric', 'pageviews')
            ->assertJsonPath('uv_approximate', false)
            ->assertJsonPath('conversion_available', false)
            ->assertJsonPath('totals.unique_visits', null)
            ->assertJsonPath('totals.clicks', null)
            ->assertJsonPath('totals.conversion_principal', null)
            ->assertJsonPath('totals.conversion_by_pageview', null)
            ->assertJsonPath('legacy.visits', 0)
            ->assertJsonPath('legacy.conversion_by_pageview', null);

        $warnings = $response->json('warnings');
        foreach (['no_data', 'uv_unavailable', 'clicks_unavailable', 'conversion_unavailable'] as $warning) {
            $this->assertContains($warning, $warnings);
        }

        $this->assertSame(null, $response->json('series.0.visits'));

        $data = $response->json('ranking.data');
        $this->assertSame($productoId, $data[0]['producto_id']);
        $this->assertSame(0, $data[0]['visits']);
        $this->assertNull($data[0]['unique_visits']);
        $this->assertNull($data[0]['clicks']);
    }

    public function test_legacy_is_separate_and_sums_duplicated_visit_rows()
    {
        $productoId = $this->makeProduct(['nombre' => 'Legacy Duplicado']);
        $this->makeVisitPost($productoId, 60, 2);
        $this->makeVisitPost($productoId, 40, 3);

        $response = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-10-26&to=2026-11-24')
            ->assertStatus(200);

        $response->assertJsonPath('legacy.scope', 'all_time')
            ->assertJsonPath('legacy.visits', 100)
            ->assertJsonPath('legacy.clicks', 5)
            ->assertJsonPath('legacy.conversion_by_pageview', 5.0);

        // El bloque legacy no se suma a los totales del rango.
        $this->assertNull($response->json('totals.visits'));
    }

    public function test_full_coverage_computes_the_three_conversions()
    {
        $productoId = $this->makeProduct(['nombre' => 'Conversion']);

        $this->seedDay($productoId, '2026-11-24', 10, 2, 1);
        $this->seedIdentity($productoId, '2026-11-24', 'identity-a');
        $this->seedIdentity($productoId, '2026-11-24', 'identity-b');
        $this->seedClick($productoId, '2026-11-24', 'identity-a');

        $response = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-11-24&to=2026-11-24')
            ->assertStatus(200);

        $response->assertJsonPath('metric', 'uv')
            ->assertJsonPath('uv_approximate', false)
            ->assertJsonPath('conversion_available', true)
            ->assertJsonPath('conversion_window.from', '2026-11-24')
            ->assertJsonPath('conversion_window.to', '2026-11-24')
            ->assertJsonPath('conversion_window.partial', false)
            ->assertJsonPath('totals.visits', 10)
            ->assertJsonPath('totals.unique_visits', 2)
            ->assertJsonPath('totals.unique_clickers', 1)
            ->assertJsonPath('totals.converted_visitors', 1)
            ->assertJsonPath('totals.clicks', 1)
            ->assertJsonPath('totals.conversion_principal', 50.0)
            ->assertJsonPath('totals.conversion_by_pageview', 10.0)
            ->assertJsonPath('totals.clicks_per_visitor', 0.5);

        $row = $response->json('ranking.data.0');
        $this->assertSame($productoId, $row['producto_id']);
        $this->assertSame(50.0, $row['conversion_principal']);
        $this->assertSame(10.0, $row['conversion_by_pageview']);
        $this->assertSame(0.5, $row['clicks_per_visitor']);
        $this->assertNotContains('conversion_window_partial', $response->json('warnings'));
    }

    public function test_partial_identity_coverage_is_approximate_and_never_claims_conversion()
    {
        $productoId = $this->makeProduct(['nombre' => 'Parcial']);

        $this->seedDay($productoId, '2026-11-22', 5, null, 0);
        $this->seedDay($productoId, '2026-11-24', 5, 2, 0);
        $this->seedIdentity($productoId, '2026-11-24', 'identity-a');
        $this->seedIdentity($productoId, '2026-11-24', 'identity-b');

        $response = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-11-22&to=2026-11-24')
            ->assertStatus(200);

        $response->assertJsonPath('uv_approximate', true)
            ->assertJsonPath('conversion_available', false)
            ->assertJsonPath('totals.unique_visits', 2)
            ->assertJsonPath('totals.conversion_principal', null);

        $this->assertContains('uv_approximate', $response->json('warnings'));
        $this->assertContains('conversion_unavailable', $response->json('warnings'));
        $this->assertNull($response->json('series.0.unique_visits'));
        $this->assertSame(2, $response->json('series.2.unique_visits'));
    }

    public function test_partial_click_coverage_nulls_uncovered_days_but_computes_the_conversion_window()
    {
        $productoId = $this->makeProduct(['nombre' => 'Clicks Parciales']);

        $this->seedDay($productoId, '2026-11-22', 5, 2, 0);
        $this->seedDay($productoId, '2026-11-24', 5, 2, 2);
        $this->seedIdentity($productoId, '2026-11-22', 'visitor-a');
        $this->seedIdentity($productoId, '2026-11-24', 'visitor-b');
        $this->seedClick($productoId, '2026-11-24', 'visitor-b');
        $this->seedClick($productoId, '2026-11-24', 'visitor-b');

        $response = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-11-22&to=2026-11-24')
            ->assertStatus(200);

        $this->assertNull($response->json('series.0.clicks'));
        $this->assertSame(2, $response->json('series.2.clicks'));
        $this->assertSame(2, $response->json('totals.clicks'));
        // Ventana 2026-11-24..2026-11-24: 2 clicks / 5 pageviews.
        $this->assertSame(40.0, $response->json('totals.conversion_by_pageview'));
        $this->assertContains('conversion_window_partial', $response->json('warnings'));
        $this->assertContains('conversion_unavailable', $response->json('warnings'));
    }

    public function test_click_before_visit_recovers_coverage_and_uv()
    {
        Carbon::setTestNow('2026-11-25 02:00:00');
        config(['analytics.enabled' => true]);

        $productoId = $this->makeProduct(['nombre' => 'Click First']);
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $this->withCookie('anonymous_user_id', $uuid)->get('/redirect/'.$productoId)->assertStatus(302);
        $this->withCookie('anonymous_user_id', $uuid)->get('/content/Click-First')->assertStatus(200);

        $response = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-11-24&to=2026-11-24')
            ->assertStatus(200);

        $response->assertJsonPath('metric', 'uv')
            ->assertJsonPath('uv_approximate', false)
            ->assertJsonPath('coverage.identities_from', '2026-11-24')
            ->assertJsonPath('totals.unique_visits', 1)
            ->assertJsonPath('totals.converted_visitors', 1)
            ->assertJsonPath('conversion_available', true)
            ->assertJsonPath('totals.conversion_principal', 100.0);

        $this->assertNotContains('uv_unavailable', $response->json('warnings'));
    }

    public function test_a_click_only_day_is_covered_with_zero_uv()
    {
        Carbon::setTestNow('2026-11-25 02:00:00');
        config(['analytics.enabled' => true]);

        $productoId = $this->makeProduct(['nombre' => 'Solo Click']);

        $this->get('/redirect/'.$productoId)->assertStatus(302);

        $response = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-11-24&to=2026-11-24')
            ->assertStatus(200);

        $response->assertJsonPath('metric', 'uv')
            ->assertJsonPath('coverage.identities_from', '2026-11-24')
            ->assertJsonPath('totals.unique_visits', 0)
            ->assertJsonPath('totals.clicks', 1)
            ->assertJsonPath('series.0.unique_visits', 0)
            ->assertJsonPath('totals.conversion_principal', null);

        $this->assertNotContains('uv_unavailable', $response->json('warnings'));
    }

    public function test_backfilled_days_without_identity_capture_stay_uncovered()
    {
        $productoId = $this->makeProduct(['nombre' => 'Backfill Sin Captura']);

        $this->seedDay($productoId, '2026-11-23', 10, null, 0);
        $this->seedDay($productoId, '2026-11-24', 5, 2, 0);
        $this->seedIdentity($productoId, '2026-11-24', 'backfill-a');
        $this->seedIdentity($productoId, '2026-11-24', 'backfill-b');

        $response = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-11-23&to=2026-11-24')
            ->assertStatus(200);

        $response->assertJsonPath('coverage.identities_from', '2026-11-24')
            ->assertJsonPath('uv_approximate', true)
            ->assertJsonPath('totals.unique_visits', 2);

        $this->assertNull($response->json('series.0.unique_visits'));
        $this->assertSame(2, $response->json('series.1.unique_visits'));
    }

    public function test_the_payload_stays_under_512kb_with_365_days_and_fifty_rows()
    {
        Carbon::setTestNow('2026-11-25 02:00:00');

        $conDatos = $this->makeProduct(['nombre' => 'Payload 365']);
        $rows = [];

        for ($i = 0; $i < 365; $i++) {
            $date = Carbon::parse('2026-11-24')->subDays($i)->toDateString();
            $rows[] = [
                'producto_id' => $conDatos,
                'date' => $date,
                'visits' => 3,
                'unique_visits' => 1,
                'clicks' => $date === '2025-11-25' ? 1 : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('post_stats_daily')->insert($rows);

        foreach (range(1, 49) as $i) {
            $this->makeProduct(['nombre' => 'Payload Extra '.$i]);
        }

        $uri = '/admin/analitica/metricas?'.http_build_query([
            'from' => '2025-11-25',
            'to' => '2026-11-24',
            'per_page' => 50,
        ]);

        $response = $this->asAdmin()->getJson($uri)->assertStatus(200);

        $this->assertSame(365, count($response->json('series')));
        $this->assertSame(50, $response->json('ranking.per_page'));
        $this->assertSame(50, count($response->json('ranking.data')));
        $this->assertLessThan(524288, strlen($response->getContent()));
        $this->assertLessThanOrEqual(6, $this->countAnalyticsQueriesFor($uri));
    }

    public function test_the_endpoint_query_count_is_constant_with_ten_thousand_products()
    {
        $categoriaId = $this->makeCategoria();
        $now = now()->toDateTimeString();
        $chunk = [];

        for ($i = 1; $i <= 10000; $i++) {
            $chunk[] = [
                'nombre' => 'Catalogo '.$i,
                'imagen' => 'https://cdn.example/product.jpg',
                'categorias_id' => $categoriaId,
                'estado' => 1,
                'NombreLink' => 'PCK 1',
                'hotLink' => 'https://example.com/hot',
                'publication_date' => '2026-11-01 00:00:00',
                'isVideo' => '0',
                'url_video' => '',
                'tags' => 'alpha',
                'published' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($chunk) === 1000) {
                DB::table('productos')->insert($chunk);
                $chunk = [];
            }
        }

        $uri = '/admin/analitica/metricas?from=2026-11-24&to=2026-11-24&per_page=50';

        $response = $this->asAdmin()->getJson($uri)->assertStatus(200);

        $this->assertSame(10000, $response->json('ranking.total'));
        $this->assertSame(50, count($response->json('ranking.data')));
        $this->assertLessThanOrEqual(6, $this->countAnalyticsQueriesFor($uri));
    }

    public function test_a_backfilled_day_after_capture_starts_makes_uv_approximate()
    {
        $productoId = $this->makeProduct(['nombre' => 'Cobertura No Contigua']);

        // 23/11: captura viva solo-click (0 UV real). 24/11: fila backfilleada.
        $this->seedDay($productoId, '2026-11-23', 0, 0, 1);
        $this->seedDay($productoId, '2026-11-24', 10, null, 0);

        $response = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-11-23&to=2026-11-24')
            ->assertStatus(200);

        $response->assertJsonPath('metric', 'uv')
            ->assertJsonPath('uv_approximate', true)
            ->assertJsonPath('conversion_available', false)
            ->assertJsonPath('series.0.unique_visits', 0)
            ->assertJsonPath('series.1.unique_visits', null)
            ->assertJsonPath('totals.unique_visits', 0);

        $this->assertContains('uv_approximate', $response->json('warnings'));
        $this->assertContains('conversion_unavailable', $response->json('warnings'));
        $this->assertNotContains('uv_unavailable', $response->json('warnings'));
    }

    public function test_a_range_starting_on_a_backfilled_day_does_not_claim_exact_uv()
    {
        $productoId = $this->makeProduct(['nombre' => 'Empieza Backfill']);

        $this->seedDay($productoId, '2026-11-23', 0, 0, 1);
        $this->seedDay($productoId, '2026-11-24', 10, null, 0);

        $response = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-11-24&to=2026-11-24')
            ->assertStatus(200);

        $response->assertJsonPath('metric', 'pageviews')
            ->assertJsonPath('uv_approximate', false)
            ->assertJsonPath('totals.unique_visits', null)
            ->assertJsonPath('series.0.unique_visits', null);

        $this->assertContains('uv_unavailable', $response->json('warnings'));
        $this->assertNotContains('uv_approximate', $response->json('warnings'));
    }

    public function test_a_fully_covered_range_has_no_coverage_warnings()
    {
        $productoId = $this->makeProduct(['nombre' => 'Cobertura Completa']);

        $this->seedDay($productoId, '2026-11-24', 10, 2, 1);
        $this->seedIdentity($productoId, '2026-11-24', 'completa-a');
        $this->seedIdentity($productoId, '2026-11-24', 'completa-b');
        $this->seedClick($productoId, '2026-11-24', 'completa-a');

        $response = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-11-24&to=2026-11-24')
            ->assertStatus(200);

        $response->assertJsonPath('metric', 'uv')
            ->assertJsonPath('uv_approximate', false)
            ->assertJsonPath('conversion_available', true)
            ->assertJsonPath('totals.unique_visits', 2);

        $this->assertSame([], $response->json('warnings'));
    }

    public function test_a_fully_backfilled_range_reports_uv_unavailable()
    {
        $productoId = $this->makeProduct(['nombre' => 'Todo Backfill']);

        $this->seedDay($productoId, '2026-11-24', 10, null, 0);

        $response = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-11-24&to=2026-11-24')
            ->assertStatus(200);

        $response->assertJsonPath('metric', 'pageviews')
            ->assertJsonPath('uv_approximate', false)
            ->assertJsonPath('totals.unique_visits', null)
            ->assertJsonPath('series.0.unique_visits', null);

        $this->assertContains('uv_unavailable', $response->json('warnings'));
    }

    public function test_a_mixed_day_is_not_reported_as_exact()
    {
        $vivo = $this->makeProduct(['nombre' => 'Mixto Vivo']);
        $backfill = $this->makeProduct(['nombre' => 'Mixto Backfill']);

        $this->seedDay($vivo, '2026-11-24', 5, 2, 1);
        $this->seedIdentity($vivo, '2026-11-24', 'mixto-a');
        $this->seedIdentity($vivo, '2026-11-24', 'mixto-b');
        $this->seedClick($vivo, '2026-11-24', 'mixto-a');
        $this->seedDay($backfill, '2026-11-24', 10, null, 0);

        $response = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-11-24&to=2026-11-24')
            ->assertStatus(200);
        $response->assertJsonPath('uv_approximate', true)
            ->assertJsonPath('conversion_available', false)
            ->assertJsonPath('series.0.unique_visits', null)
            ->assertJsonPath('totals.unique_visits', 2);

        $this->assertContains('uv_approximate', $response->json('warnings'));
    }

    public function test_the_service_failure_returns_a_controlled_500()
    {
        $failing = new class(new AnalyticsTimeService()) extends PostMetricsService {
            public function metrics(array $query = [])
            {
                throw new \RuntimeException('boom');
            }
        };

        $this->app->instance(PostMetricsService::class, $failing);

        $this->asAdmin()
            ->getJson('/admin/analitica/metricas')
            ->assertStatus(500)
            ->assertJson(['error' => 'metrics_unavailable']);
    }

    public function test_the_endpoint_uses_six_queries_no_matter_how_many_products()
    {
        $productoId = $this->makeProduct(['nombre' => 'Uno']);
        $this->seedDay($productoId, '2026-11-24', 3, 1, 1);
        $this->seedIdentity($productoId, '2026-11-24', 'visitor-one');

        $small = $this->countMetricQueries();

        foreach (range(1, 49) as $i) {
            $this->makeProduct(['nombre' => 'Catalogo '.$i]);
        }

        $large = $this->countMetricQueries();

        $this->assertSame($small, $large);
    }

    public function test_the_payload_has_the_contracted_shape()
    {
        $response = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-11-24&to=2026-11-24')
            ->assertStatus(200);

        $this->assertSame([
            'conversion_available',
            'conversion_window',
            'coverage',
            'from',
            'legacy',
            'metric',
            'ranking',
            'series',
            'timezone',
            'to',
            'totals',
            'uv_approximate',
            'warnings',
        ], $this->sortedKeys($response->json()));

        $this->assertSame(
            ['clicks_from', 'identities_from', 'visits_from'],
            $this->sortedKeys($response->json('coverage'))
        );
        $this->assertSame(
            ['clicks', 'clicks_per_visitor', 'conversion_by_pageview', 'conversion_principal', 'converted_visitors', 'unique_clickers', 'unique_visits', 'visits'],
            $this->sortedKeys($response->json('totals'))
        );
        $this->assertSame(
            ['clicks', 'conversion_by_pageview', 'scope', 'visits'],
            $this->sortedKeys($response->json('legacy'))
        );
        $this->assertSame(
            ['current_page', 'data', 'last_page', 'per_page', 'total'],
            $this->sortedKeys($response->json('ranking'))
        );
        $this->assertSame(
            ['clicks', 'date', 'unique_visits', 'visits'],
            $this->sortedKeys($response->json('series.0'))
        );
    }

    public function test_ranking_breaks_metric_ties_by_clicks_descending()
    {
        $menos = $this->makeProduct(['nombre' => 'Menos Clicks']);
        $mas = $this->makeProduct(['nombre' => 'Mas Clicks']);

        $this->seedDay($menos, '2026-11-24', 10, 2, 1);
        $this->seedDay($mas, '2026-11-24', 10, 2, 5);
        $this->seedIdentity($menos, '2026-11-24', 'menos-a');
        $this->seedIdentity($menos, '2026-11-24', 'menos-b');
        $this->seedIdentity($mas, '2026-11-24', 'mas-a');
        $this->seedIdentity($mas, '2026-11-24', 'mas-b');
        $this->seedClick($menos, '2026-11-24', 'menos-a');
        foreach (range(1, 5) as $i) {
            $this->seedClick($mas, '2026-11-24', 'mas-a');
        }

        $ids = array_column(
            $this->asAdmin()
                ->getJson('/admin/analitica/metricas?from=2026-11-24&to=2026-11-24')
                ->assertStatus(200)
                ->json('ranking.data'),
            'producto_id'
        );

        $this->assertSame([$mas, $menos], $ids);
    }

    public function test_include_inactive_extends_the_universe_to_every_product()
    {
        $activo = $this->makeProduct(['nombre' => 'Activo']);
        $inactivo = $this->makeProduct(['nombre' => 'Inactivo', 'estado' => 0]);

        $this->seedDay($activo, '2026-11-24', 10, 2, 1);
        $this->seedIdentity($activo, '2026-11-24', 'activo-a');
        $this->seedClick($activo, '2026-11-24', 'activo-a');

        $defaultIds = array_column(
            $this->asAdmin()
                ->getJson('/admin/analitica/metricas?from=2026-11-24&to=2026-11-24')
                ->assertStatus(200)
                ->json('ranking.data'),
            'producto_id'
        );
        $this->assertContains($activo, $defaultIds);
        $this->assertNotContains($inactivo, $defaultIds);

        $all = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-11-24&to=2026-11-24&include_inactive=1')
            ->assertStatus(200);

        $this->assertContains($inactivo, array_column($all->json('ranking.data'), 'producto_id'));
        $this->assertSame(2, $all->json('ranking.total'));
    }

    public function test_ranking_lists_the_whole_universe_with_zero_rows_last()
    {
        for ($i = 1; $i <= 3; $i++) {
            $productoId = $this->makeProduct(['nombre' => 'Con Datos '.$i]);
            $this->seedDay($productoId, '2026-11-24', 10 - $i, 2, 1);
            $this->seedIdentity($productoId, '2026-11-24', 'ident-'.$i.'-a');
            $this->seedIdentity($productoId, '2026-11-24', 'ident-'.$i.'-b');
            $this->seedClick($productoId, '2026-11-24', 'ident-'.$i.'-a');
        }

        for ($i = 1; $i <= 17; $i++) {
            $this->makeProduct(['nombre' => 'Sin Datos '.$i]);
        }

        $response = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-11-24&to=2026-11-24&per_page=50')
            ->assertStatus(200);

        $this->assertSame(20, $response->json('ranking.total'));
        $this->assertSame(50, $response->json('ranking.per_page'));
        $this->assertCount(20, $response->json('ranking.data'));

        $data = $response->json('ranking.data');
        foreach (array_slice($data, 0, 3) as $row) {
            $this->assertGreaterThan(0, $row['visits']);
        }
        foreach (array_slice($data, 3) as $row) {
            $this->assertSame(0, $row['visits']);
            $this->assertSame(0, $row['unique_visits']);
            $this->assertSame(0, $row['clicks']);
        }
    }

    public function test_pagination_reports_totals_and_an_out_of_range_page_is_empty()
    {
        for ($i = 1; $i <= 3; $i++) {
            $productoId = $this->makeProduct(['nombre' => 'Pagina '.$i]);
            $this->seedDay($productoId, '2026-11-24', 10, 2, 1);
            $this->seedIdentity($productoId, '2026-11-24', 'pag-'.$i);
            $this->seedClick($productoId, '2026-11-24', 'pag-'.$i);
        }

        $response = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-11-24&to=2026-11-24&per_page=2&page=99')
            ->assertStatus(200);

        $response->assertJsonPath('ranking.current_page', 99)
            ->assertJsonPath('ranking.per_page', 2)
            ->assertJsonPath('ranking.total', 3)
            ->assertJsonPath('ranking.last_page', 2)
            ->assertJsonPath('ranking.data', []);
    }

    public function test_per_page_out_of_range_is_clamped_to_the_effective_value()
    {
        $this->makeProduct(['nombre' => 'Per Page']);

        $high = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-11-24&to=2026-11-24&per_page=999')
            ->assertStatus(200);
        $high->assertJsonPath('ranking.per_page', 50);

        $low = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-11-24&to=2026-11-24&per_page=0')
            ->assertStatus(200);
        $low->assertJsonPath('ranking.per_page', 1);
    }

    public function test_the_ranking_orders_by_the_effective_metric_not_by_clicks()
    {
        // Producto sin datos con id menor: debe quedar al final igual.
        $sinDatos = $this->makeProduct(['nombre' => 'Sin Datos Primero']);

        $alta = $this->makeProduct(['nombre' => 'Metrica Alta']);
        $this->seedDay($alta, '2026-11-24', 100, 10, 0);
        foreach (range(1, 10) as $i) {
            $this->seedIdentity($alta, '2026-11-24', 'alta-'.$i);
        }

        $baja = $this->makeProduct(['nombre' => 'Metrica Baja']);
        $this->seedDay($baja, '2026-11-24', 10, 1, 5);
        $this->seedIdentity($baja, '2026-11-24', 'baja-1');
        foreach (range(1, 5) as $i) {
            $this->seedClick($baja, '2026-11-24', 'baja-1');
        }

        foreach (['uv', 'pageviews'] as $metric) {
            $ids = array_column(
                $this->asAdmin()
                    ->getJson('/admin/analitica/metricas?from=2026-11-24&to=2026-11-24&metric='.$metric)
                    ->assertStatus(200)
                    ->json('ranking.data'),
                'producto_id'
            );

            $this->assertSame([$alta, $baja, $sinDatos], $ids, 'Orden incorrecto para metric='.$metric);
        }
    }

    public function test_partial_click_coverage_computes_conversion_on_the_covered_window()
    {
        $productoA = $this->makeProduct(['nombre' => 'Ventana A']);
        $this->seedDay($productoA, '2026-11-01', 10, 2, 0);
        $this->seedDay($productoA, '2026-11-24', 5, 2, 5);
        $this->seedIdentity($productoA, '2026-11-01', 'ventana-a1');
        $this->seedIdentity($productoA, '2026-11-01', 'ventana-a2');
        $this->seedIdentity($productoA, '2026-11-24', 'ventana-a1');
        $this->seedIdentity($productoA, '2026-11-24', 'ventana-a2');
        foreach (range(1, 5) as $i) {
            $this->seedClick($productoA, '2026-11-24', 'ventana-a1');
        }

        // Pageviews solo antes de la ventana: la conversion debe ser null.
        $productoB = $this->makeProduct(['nombre' => 'Ventana B']);
        $this->seedDay($productoB, '2026-11-01', 10, 2, 0);
        $this->seedIdentity($productoB, '2026-11-01', 'ventana-b1');

        // Pageviews y clicks solo dentro de la ventana.
        $productoC = $this->makeProduct(['nombre' => 'Ventana C']);
        $this->seedDay($productoC, '2026-11-24', 4, 2, 1);
        $this->seedIdentity($productoC, '2026-11-24', 'ventana-c1');
        $this->seedClick($productoC, '2026-11-24', 'ventana-c1');

        $response = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-10-26&to=2026-11-24&per_page=50')
            ->assertStatus(200);

        $response->assertJsonPath('conversion_window.from', '2026-11-24')
            ->assertJsonPath('conversion_window.to', '2026-11-24')
            ->assertJsonPath('conversion_window.partial', true)
            ->assertJsonPath('totals.conversion_by_pageview', 66.67)
            ->assertJsonPath('totals.conversion_principal', null);

        $this->assertContains('conversion_window_partial', $response->json('warnings'));

        $rows = [];
        foreach ($response->json('ranking.data') as $row) {
            $rows[$row['producto_id']] = $row;
        }

        $this->assertSame(100.0, $rows[$productoA]['conversion_by_pageview']);        $this->assertNull($rows[$productoB]['conversion_by_pageview']);
        $this->assertSame(25.0, $rows[$productoC]['conversion_by_pageview']);
    }

    public function test_clicks_unavailable_has_no_conversion_window()
    {
        $productoId = $this->makeProduct(['nombre' => 'Sin Clicks']);
        $this->seedDay($productoId, '2026-11-24', 10, 2, 0);
        $this->seedIdentity($productoId, '2026-11-24', 'sinclick-a');

        $response = $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-11-24&to=2026-11-24')
            ->assertStatus(200);

        $response->assertJsonPath('conversion_window.from', null)
            ->assertJsonPath('conversion_window.to', null)
            ->assertJsonPath('conversion_window.partial', false)
            ->assertJsonPath('totals.conversion_by_pageview', null);

        $this->assertContains('clicks_unavailable', $response->json('warnings'));
        $this->assertNotContains('conversion_window_partial', $response->json('warnings'));
    }

    private function seedDay(int $productoId, string $date, int $visits, $uniqueVisits, int $clicks)
    {
        DB::table('post_stats_daily')->insert([
            'producto_id' => $productoId,
            'date' => $date,
            'visits' => $visits,
            'unique_visits' => $uniqueVisits,
            'clicks' => $clicks,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedIdentity(int $productoId, string $date, string $identity)
    {
        DB::table('post_visit_identities')->insert([
            'producto_id' => $productoId,
            'date' => $date,
            'identity' => $identity,
            'created_at' => now(),
        ]);
    }

    private function seedClick(int $productoId, string $date, string $identity)
    {
        DB::table('post_clicks')->insert([
            'producto_id' => $productoId,
            'date' => $date,
            'identity' => $identity,
            'source' => 'web',
            'referer' => null,
            'user_agent' => null,
            'ip_hash' => str_repeat('a', 64),
            'created_at' => now(),
        ]);
    }

    /**
     * Sentencias del servicio de metricas. Excluye el middleware legacy de
     * tracking anonimo, que corre en todo el grupo web.
     */
    private function countMetricQueries(): int
    {
        return $this->countAnalyticsQueriesFor('/admin/analitica/metricas?from=2026-11-24&to=2026-11-24');
    }

    /**
     * Sentencias del servicio de metricas para una URL concreta. Excluye el
     * middleware legacy de tracking anonimo.
     */
    private function countAnalyticsQueriesFor(string $uri): int
    {
        $queries = [];

        DB::listen(function ($query) use (&$queries) {
            if (preg_match('/(post_stats_daily|post_visit_identities|post_clicks|visit_posts|from `productos`)/', $query->sql)) {
                $queries[] = $query->sql;
            }
        });

        $this->asAdmin()->getJson($uri)->assertStatus(200);

        return count($queries);
    }

    private function sortedKeys(array $item): array
    {
        $keys = array_keys($item);
        sort($keys);

        return $keys;
    }
}
