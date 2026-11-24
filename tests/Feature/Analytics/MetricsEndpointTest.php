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

    public function test_partial_click_coverage_nulls_uncovered_days_and_the_legacy_conversion()
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
        $this->assertNull($response->json('totals.conversion_by_pageview'));
        $this->assertContains('conversion_unavailable', $response->json('warnings'));
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
        $queries = [];

        DB::listen(function ($query) use (&$queries) {
            if (preg_match('/(post_stats_daily|post_visit_identities|post_clicks|visit_posts|from `productos`)/', $query->sql)) {
                $queries[] = $query->sql;
            }
        });

        $this->asAdmin()
            ->getJson('/admin/analitica/metricas?from=2026-11-24&to=2026-11-24')
            ->assertStatus(200);

        $this->assertLessThanOrEqual(6, count($queries), implode("
", $queries));

        return count($queries);
    }

    private function sortedKeys(array $item): array
    {
        $keys = array_keys($item);
        sort($keys);

        return $keys;
    }
}
