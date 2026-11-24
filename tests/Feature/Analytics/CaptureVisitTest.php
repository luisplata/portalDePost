<?php

namespace Tests\Feature\Analytics;

use App\Services\AnalyticsTimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Captura de visita (T-S2b-05..08, REQ-S2-01, S2-04..S2-08, REG-01).
 *
 * La captura es fail-open y dual write: `visit_posts` y `log_visits` siguen
 * creciendo igual, y el dia del hecho es local (America/Bogota).
 */
class CaptureVisitTest extends AnalyticsTestSupport
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_visit_records_pageview_and_unique_visitor_on_the_local_day()
    {
        Carbon::setTestNow('2026-11-25 02:00:00');
        config(['analytics.enabled' => true]);

        $productoId = $this->makeProduct(['nombre' => 'Visita Uno']);
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $this->withCookie('anonymous_user_id', $uuid)
            ->get('/content/Visita-Uno')
            ->assertStatus(200);

        $stats = DB::table('post_stats_daily')->where('producto_id', $productoId)->first();

        $this->assertNotNull($stats);
        $this->assertSame('2026-11-24', $stats->date);
        $this->assertSame(1, (int) $stats->visits);
        $this->assertSame(1, (int) $stats->unique_visits);
        $this->assertSame(0, (int) $stats->clicks);

        $identity = DB::table('post_visit_identities')->where('producto_id', $productoId)->first();

        $this->assertNotNull($identity);
        $this->assertSame($uuid, $identity->identity);
        $this->assertSame('2026-11-24', $identity->date);

        $this->assertSame(
            1,
            (int) DB::table('visit_posts')->where('producto_id', $productoId)->value('visita')
        );
        $this->assertSame(1, DB::table('log_visits')->where('producto_id', $productoId)->count());
    }

    public function test_repeated_visits_the_same_day_count_pageviews_but_one_unique()
    {
        Carbon::setTestNow('2026-11-25 02:00:00');
        config(['analytics.enabled' => true]);

        $productoId = $this->makeProduct(['nombre' => 'Visita Dos']);

        $this->withCookie('anonymous_user_id', 'visitor-same-day')
            ->get('/content/Visita-Dos')
            ->assertStatus(200);
        $this->withCookie('anonymous_user_id', 'visitor-same-day')
            ->get('/content/Visita-Dos')
            ->assertStatus(200);

        $stats = DB::table('post_stats_daily')->where('producto_id', $productoId)->first();

        $this->assertSame(2, (int) $stats->visits);
        $this->assertSame(1, (int) $stats->unique_visits);
        $this->assertSame(1, DB::table('post_visit_identities')->where('producto_id', $productoId)->count());
    }

    public function test_the_daily_dedupe_resets_at_local_midnight()
    {
        config(['analytics.enabled' => true]);

        $productoId = $this->makeProduct(['nombre' => 'Visita Tres']);

        Carbon::setTestNow('2026-11-25 02:00:00');
        $this->withCookie('anonymous_user_id', 'visitor-two-days')
            ->get('/content/Visita-Tres')
            ->assertStatus(200);

        Carbon::setTestNow('2026-11-26 02:00:00');
        $this->withCookie('anonymous_user_id', 'visitor-two-days')
            ->get('/content/Visita-Tres')
            ->assertStatus(200);

        $stats = DB::table('post_stats_daily')
            ->where('producto_id', $productoId)
            ->orderBy('date')
            ->get();

        $this->assertCount(2, $stats);
        $this->assertSame('2026-11-24', $stats[0]->date);
        $this->assertSame(1, (int) $stats[0]->unique_visits);
        $this->assertSame('2026-11-25', $stats[1]->date);
        $this->assertSame(1, (int) $stats[1]->unique_visits);
        $this->assertSame(2, DB::table('post_visit_identities')->where('producto_id', $productoId)->count());
    }

    public function test_api_visits_are_captured_from_the_api_group()
    {
        Carbon::setTestNow('2026-11-25 02:00:00');
        config(['analytics.enabled' => true]);

        $productoId = $this->makeProduct(['nombre' => 'Visita Api']);

        $this->getJson('/api/model/'.$productoId)->assertStatus(200);

        $stats = DB::table('post_stats_daily')->where('producto_id', $productoId)->first();
        $identity = DB::table('post_visit_identities')->where('producto_id', $productoId)->first();

        $this->assertSame(1, (int) $stats->visits);
        $this->assertSame(1, (int) $stats->unique_visits);
        $this->assertStringStartsWith('ip:', $identity->identity);
        $this->assertSame(
            1,
            (int) DB::table('visit_posts')->where('producto_id', $productoId)->value('visita')
        );
    }

    public function test_bots_are_excluded_from_analytics_but_not_from_legacy_counters()
    {
        Carbon::setTestNow('2026-11-25 02:00:00');
        config(['analytics.enabled' => true]);

        $productoId = $this->makeProduct(['nombre' => 'Visita Bot']);

        $this->withHeader('User-Agent', 'Googlebot/2.1')
            ->get('/content/Visita-Bot')
            ->assertStatus(200);

        $this->assertSame(0, DB::table('post_stats_daily')->where('producto_id', $productoId)->count());
        $this->assertSame(0, DB::table('post_visit_identities')->where('producto_id', $productoId)->count());
        $this->assertSame(
            1,
            (int) DB::table('visit_posts')->where('producto_id', $productoId)->value('visita')
        );
        $this->assertSame(1, DB::table('log_visits')->where('producto_id', $productoId)->count());
    }

    public function test_an_empty_user_agent_is_not_counted()
    {
        config(['analytics.enabled' => true]);

        $productoId = $this->makeProduct(['nombre' => 'Visita Sin Ua']);

        $this->withHeader('User-Agent', '')
            ->get('/content/Visita-Sin-Ua')
            ->assertStatus(200);

        $this->assertSame(0, DB::table('post_stats_daily')->where('producto_id', $productoId)->count());
    }

    public function test_prefetch_requests_are_not_counted()
    {
        config(['analytics.enabled' => true]);

        $productoId = $this->makeProduct(['nombre' => 'Visita Prefetch']);

        $this->withHeader('Purpose', 'prefetch')
            ->get('/content/Visita-Prefetch')
            ->assertStatus(200);

        $this->assertSame(0, DB::table('post_stats_daily')->where('producto_id', $productoId)->count());
    }

    public function test_admin_sessions_are_excluded_from_the_visit()
    {
        config(['analytics.enabled' => true]);

        $productoId = $this->makeProduct(['nombre' => 'Visita Admin']);

        $this->asAdmin()
            ->get('/content/Visita-Admin')
            ->assertStatus(200);

        $this->assertSame(0, DB::table('post_stats_daily')->where('producto_id', $productoId)->count());
        $this->assertSame(
            1,
            (int) DB::table('visit_posts')->where('producto_id', $productoId)->value('visita')
        );
    }

    public function test_the_flag_off_writes_nothing_and_adds_no_queries()
    {
        config(['analytics.enabled' => false]);

        $productoId = $this->makeProduct(['nombre' => 'Visita Apagada']);

        $analyticsQueries = [];
        DB::listen(function ($query) use (&$analyticsQueries) {
            if (preg_match('/post_(stats_daily|visit_identities|clicks)/', $query->sql)) {
                $analyticsQueries[] = $query->sql;
            }
        });

        $this->get('/content/Visita-Apagada')->assertStatus(200);

        $this->assertSame([], $analyticsQueries);
        $this->assertSame(0, DB::table('post_stats_daily')->where('producto_id', $productoId)->count());
        $this->assertSame(
            1,
            (int) DB::table('visit_posts')->where('producto_id', $productoId)->value('visita')
        );
    }

    public function test_visit_capture_adds_at_most_four_queries_and_does_not_grow_with_the_catalog()
    {
        $productoId = $this->makeProduct(['nombre' => 'Visita Queries']);

        config(['analytics.enabled' => false]);
        $this->assertSame([], $this->analyticsQueriesFor('/content/Visita-Queries'));

        config(['analytics.enabled' => true]);
        $on = $this->analyticsQueriesFor('/content/Visita-Queries');

        $this->assertLessThanOrEqual(4, count($on));
        $this->assertGreaterThanOrEqual(2, count($on));

        foreach (range(1, 30) as $i) {
            $this->makeProduct(['nombre' => 'Catalogo '.$i]);
        }

        $onMany = $this->analyticsQueriesFor('/content/Visita-Queries');

        $this->assertSame(count($on), count($onMany));
        $this->assertGreaterThan(0, DB::table('post_stats_daily')->where('producto_id', $productoId)->count());
    }

    public function test_a_visit_after_a_click_recovers_the_daily_unique_visitor()
    {
        Carbon::setTestNow('2026-11-25 02:00:00');
        config(['analytics.enabled' => true]);

        $productoId = $this->makeProduct(['nombre' => 'Click Luego Visita']);
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $this->withCookie('anonymous_user_id', $uuid)
            ->get('/redirect/'.$productoId)
            ->assertStatus(302);
        $this->withCookie('anonymous_user_id', $uuid)
            ->get('/content/Click-Luego-Visita')
            ->assertStatus(200);

        $stats = DB::table('post_stats_daily')->where('producto_id', $productoId)->first();

        $this->assertSame(1, (int) $stats->visits);
        $this->assertSame(1, (int) $stats->clicks);
        $this->assertSame(1, (int) $stats->unique_visits);
        $this->assertSame(1, DB::table('post_visit_identities')->where('producto_id', $productoId)->count());
    }

    public function test_capture_failures_are_logged_and_do_not_break_the_page()
    {
        config(['analytics.enabled' => true]);

        $productoId = $this->makeProduct(['nombre' => 'Visita Fail Open']);

        $this->app->instance(AnalyticsTimeService::class, new class extends AnalyticsTimeService {
            public function today()
            {
                throw new \RuntimeException('store down');
            }
        });

        Log::spy();

        $this->withCookie('anonymous_user_id', 'visitor-fail-open')
            ->get('/content/Visita-Fail-Open')
            ->assertStatus(200);

        $this->assertSame(0, DB::table('post_stats_daily')->where('producto_id', $productoId)->count());
        $this->assertSame(
            1,
            (int) DB::table('visit_posts')->where('producto_id', $productoId)->value('visita')
        );
        $this->assertSame(1, DB::table('log_visits')->where('producto_id', $productoId)->count());

        Log::shouldHaveReceived('error')->withArgs(function ($message, $context) use ($productoId) {
            return $message === 'analytics:capture_failed'
                && $context['type'] === 'visit'
                && $context['producto_id'] === $productoId
                && !isset($context['ip'])
                && !isset($context['user_agent']);
        });
    }

    /**
     * Sentencias que tocan los almacenes nuevos durante un request.
     *
     * El conteo total incluye queries legacy y la cache de publicidad, que no
     * forman parte del delta de NFR-01.
     */
    private function analyticsQueriesFor(string $uri): array
    {
        $queries = [];

        DB::listen(function ($query) use (&$queries) {
            if (preg_match('/post_(stats_daily|visit_identities|clicks)/', $query->sql)) {
                $queries[] = $query->sql;
            }
        });

        $this->get($uri)->assertStatus(200);

        return $queries;
    }
}
