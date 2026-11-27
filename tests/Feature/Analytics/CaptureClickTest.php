<?php

namespace Tests\Feature\Analytics;

use App\Services\AnalyticsTimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Captura de click saliente (T-S2c-01..04, REQ-S2-02, S2-05, S2-06, S2-08).
 *
 * Cada click valido deja un evento (sin dedupe) con fecha local, created_at
 * UTC y contexto truncado. Un click directo sin visita suma a clicks pero no
 * crea identidades de visita.
 */
class CaptureClickTest extends AnalyticsTestSupport
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_web_redirect_records_one_click_on_the_local_day()
    {
        Carbon::setTestNow('2026-11-25 02:00:00');
        config(['analytics.enabled' => true]);

        $productoId = $this->makeProduct([
            'nombre' => 'Click Uno',
            'hotLink' => 'https://example.com/click-uno',
        ]);

        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $response = $this->withCookie('anonymous_user_id', $uuid)
            ->withHeader('Referer', 'https://portal.test/content/Click-Uno')
            ->get('/redirect/'.$productoId);

        $response->assertStatus(302);
        $this->assertSame('https://example.com/click-uno', $response->headers->get('Location'));

        $click = DB::table('post_clicks')->where('producto_id', $productoId)->first();

        $this->assertNotNull($click);
        $this->assertSame('2026-11-24', $click->date);
        $this->assertSame('2026-11-25 02:00:00', $click->created_at);
        $this->assertSame($uuid, $click->identity);
        $this->assertSame('web', $click->source);
        $this->assertSame('https://portal.test/content/Click-Uno', $click->referer);
        $this->assertSame(
            hash('sha256', '127.0.0.1|'.config('app.key')),
            $click->ip_hash
        );

        $stats = DB::table('post_stats_daily')->where('producto_id', $productoId)->first();
        $this->assertSame(1, (int) $stats->clicks);
        $this->assertSame(0, (int) $stats->visits);
        // Un dia con captura viva marca 0 UV real; NULL queda reservado al backfill.
        $this->assertSame(0, (int) $stats->unique_visits);

        $this->assertSame(
            1,
            (int) DB::table('visit_posts')->where('producto_id', $productoId)->value('idoAlPack')
        );
    }

    public function test_a_named_redirect_also_records_the_click()
    {
        config(['analytics.enabled' => true]);

        $productoId = $this->makeProduct([
            'nombre' => 'ClickName',
            'hotLink' => 'https://example.com/click-name',
        ]);

        $response = $this->get('/redirection/ClickName');

        $response->assertStatus(302);
        $this->assertSame('https://example.com/click-name', $response->headers->get('Location'));
        $this->assertSame(1, DB::table('post_clicks')->where('producto_id', $productoId)->count());
    }

    public function test_a_product_without_hotlink_does_not_record_a_click()
    {
        config(['analytics.enabled' => true]);

        $productoId = $this->makeProduct(['nombre' => 'Sin Hotlink', 'hotLink' => null]);

        $this->get('/redirect/'.$productoId);

        $this->assertSame(0, DB::table('post_clicks')->where('producto_id', $productoId)->count());
        $this->assertSame(0, DB::table('post_stats_daily')->where('producto_id', $productoId)->count());
        // El contador legacy sigue su comportamiento actual (incrementa aunque
        // el hotLink sea nulo); la analitica es la que no cuenta el click.
        $this->assertSame(
            1,
            (int) DB::table('visit_posts')->where('producto_id', $productoId)->value('idoAlPack')
        );
    }

    public function test_api_redirects_are_tagged_as_api_source()
    {
        config(['analytics.enabled' => true]);

        $productoId = $this->makeProduct([
            'nombre' => 'Click Api',
            'hotLink' => 'https://example.com/click-api',
        ]);

        $response = $this->getJson('/api/model/redirect/id/'.$productoId);

        $response->assertStatus(200);
        $this->assertSame(['redirect_to' => 'https://example.com/click-api'], $response->json());

        $click = DB::table('post_clicks')->where('producto_id', $productoId)->first();

        $this->assertSame('api', $click->source);
        $this->assertSame(
            1,
            (int) DB::table('visit_posts')->where('producto_id', $productoId)->value('idoAlPack')
        );
    }

    public function test_clicks_are_not_deduplicated()
    {
        config(['analytics.enabled' => true]);

        $productoId = $this->makeProduct(['nombre' => 'Doble Click']);

        $this->withCookie('anonymous_user_id', 'clicker-repeat')
            ->get('/redirect/'.$productoId)
            ->assertStatus(302);
        $this->withCookie('anonymous_user_id', 'clicker-repeat')
            ->get('/redirect/'.$productoId)
            ->assertStatus(302);

        $this->assertSame(2, DB::table('post_clicks')->where('producto_id', $productoId)->count());
        $this->assertSame(
            2,
            (int) DB::table('post_stats_daily')->where('producto_id', $productoId)->value('clicks')
        );
        $this->assertSame(0, DB::table('post_visit_identities')->where('producto_id', $productoId)->count());
    }

    public function test_context_is_truncated_and_the_raw_ip_is_not_stored()
    {
        config(['analytics.enabled' => true]);

        $productoId = $this->makeProduct(['nombre' => 'Click Largo']);

        $this->withCookie('anonymous_user_id', 'clicker-long')
            ->withHeader('User-Agent', str_repeat('u', 700))
            ->withHeader('Referer', str_repeat('r', 300))
            ->get('/redirect/'.$productoId)
            ->assertStatus(302);

        $click = DB::table('post_clicks')->where('producto_id', $productoId)->first();

        $this->assertSame(255, strlen($click->user_agent));
        $this->assertSame(255, strlen($click->referer));
        $this->assertSame(64, strlen($click->ip_hash));
        $this->assertNotSame('127.0.0.1', $click->ip_hash);
    }

    public function test_a_click_only_day_is_covered_with_zero_unique_visitors()
    {
        config(['analytics.enabled' => true]);

        $productoId = $this->makeProduct(['nombre' => 'Solo Click']);

        $this->get('/redirect/'.$productoId)->assertStatus(302);

        $stats = DB::table('post_stats_daily')->where('producto_id', $productoId)->first();

        // Un dia con captura viva siempre deja un marcador no nulo: 0 real.
        $this->assertNotNull($stats->unique_visits);
        $this->assertSame(0, (int) $stats->unique_visits);
        $this->assertSame(0, DB::table('post_visit_identities')->where('producto_id', $productoId)->count());
    }

    public function test_bots_and_empty_agents_do_not_record_clicks()
    {
        config(['analytics.enabled' => true]);

        $botProduct = $this->makeProduct(['nombre' => 'Click Bot']);
        $emptyProduct = $this->makeProduct(['nombre' => 'Click Sin Ua']);

        $this->withHeader('User-Agent', 'Googlebot/2.1')
            ->get('/redirect/'.$botProduct)
            ->assertStatus(302);
        $this->withHeader('User-Agent', '')
            ->get('/redirect/'.$emptyProduct)
            ->assertStatus(302);

        $this->assertSame(0, DB::table('post_clicks')->where('producto_id', $botProduct)->count());
        $this->assertSame(0, DB::table('post_clicks')->where('producto_id', $emptyProduct)->count());
        $this->assertSame(
            1,
            (int) DB::table('visit_posts')->where('producto_id', $botProduct)->value('idoAlPack')
        );
    }

    public function test_admin_sessions_are_excluded_from_clicks()
    {
        config(['analytics.enabled' => true]);

        $productoId = $this->makeProduct(['nombre' => 'Click Admin']);

        $this->asAdmin()->get('/redirect/'.$productoId)->assertStatus(302);

        $this->assertSame(0, DB::table('post_clicks')->where('producto_id', $productoId)->count());
        $this->assertSame(
            1,
            (int) DB::table('visit_posts')->where('producto_id', $productoId)->value('idoAlPack')
        );
    }

    public function test_the_flag_off_writes_nothing_and_adds_no_queries()
    {
        config(['analytics.enabled' => false]);

        $productoId = $this->makeProduct(['nombre' => 'Click Apagado']);

        $this->assertSame([], $this->analyticsQueriesFor('/redirect/'.$productoId));
        $this->assertSame(0, DB::table('post_clicks')->where('producto_id', $productoId)->count());
        $this->assertSame(
            1,
            (int) DB::table('visit_posts')->where('producto_id', $productoId)->value('idoAlPack')
        );
    }

    public function test_click_capture_adds_at_most_four_queries_and_does_not_grow_with_the_catalog()
    {
        $productoId = $this->makeProduct(['nombre' => 'Click Queries']);

        config(['analytics.enabled' => false]);
        $this->assertSame([], $this->analyticsQueriesFor('/redirect/'.$productoId));

        config(['analytics.enabled' => true]);
        $on = $this->analyticsQueriesFor('/redirect/'.$productoId);

        $this->assertLessThanOrEqual(4, count($on));
        $this->assertGreaterThanOrEqual(2, count($on));

        foreach (range(1, 30) as $i) {
            $this->makeProduct(['nombre' => 'Catalogo Click '.$i]);
        }

        $onMany = $this->analyticsQueriesFor('/redirect/'.$productoId);

        $this->assertSame(count($on), count($onMany));
        $this->assertSame(2, DB::table('post_clicks')->where('producto_id', $productoId)->count());
    }

    public function test_capture_failures_are_logged_and_the_redirect_is_unchanged()
    {
        config(['analytics.enabled' => true]);

        $productoId = $this->makeProduct([
            'nombre' => 'Click Fail Open',
            'hotLink' => 'https://example.com/fail-open',
        ]);

        $this->app->instance(AnalyticsTimeService::class, new class extends AnalyticsTimeService {
            public function today()
            {
                throw new \RuntimeException('store down');
            }
        });

        Log::spy();

        $response = $this->get('/redirect/'.$productoId);

        $response->assertStatus(302);
        $this->assertSame('https://example.com/fail-open', $response->headers->get('Location'));
        $this->assertSame(0, DB::table('post_clicks')->where('producto_id', $productoId)->count());
        $this->assertSame(
            1,
            (int) DB::table('visit_posts')->where('producto_id', $productoId)->value('idoAlPack')
        );

        Log::shouldHaveReceived('error')->withArgs(function ($message, $context) use ($productoId) {
            return $message === 'analytics:capture_failed'
                && $context['type'] === 'click'
                && $context['producto_id'] === $productoId
                && !isset($context['ip'])
                && !isset($context['user_agent']);
        });
    }

    /**
     * Sentencias que tocan los almacenes nuevos durante un request.
     */
    private function analyticsQueriesFor(string $uri): array
    {
        $queries = [];

        DB::listen(function ($query) use (&$queries) {
            if (preg_match('/post_(stats_daily|visit_identities|clicks)/', $query->sql)) {
                $queries[] = $query->sql;
            }
        });

        $this->get($uri)->assertStatus(302);

        return $queries;
    }
}
