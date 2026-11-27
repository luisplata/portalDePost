<?php

namespace Tests\Feature\Analytics;

use App\Services\PostAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Purga de eventos crudos y retencion (T-S3d-01/02/05, REQ-S3-09).
 *
 * Solo `post_clicks` y `post_visit_identities`; los agregados diarios son
 * permanentes. La purga oportunista corre como maximo una vez cada 24 h y
 * nunca propaga un error.
 */
class PurgeEventsTest extends AnalyticsTestSupport
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase puede dejar un binding mockeado de OutputStyle en el
        // contenedor; se elimina para capturar la salida real de los comandos.
        $this->app->offsetUnset(\Illuminate\Console\OutputStyle::class);


        Carbon::setTestNow('2026-11-25 02:00:00');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_deletes_old_events_and_keeps_the_daily_aggregates()
    {
        $productoId = $this->makeProduct();

        $this->seedClick($productoId, '2026-07-28 12:00:00');
        $this->seedClick($productoId, '2026-11-24 12:00:00');
        $this->seedIdentity($productoId, '2026-07-28 12:00:00');
        $this->seedIdentity($productoId, '2026-11-24 12:00:00');
        DB::table('post_stats_daily')->insert([
            'producto_id' => $productoId,
            'date' => '2026-07-28',
            'visits' => 10,
            'unique_visits' => 3,
            'clicks' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exit = Artisan::call('analytics:purge-events');

        $this->assertSame(0, $exit);
        $this->assertSame(1, DB::table('post_clicks')->count());
        $this->assertSame('2026-11-24', substr((string) DB::table('post_clicks')->value('created_at'), 0, 10));
        $this->assertSame(1, DB::table('post_visit_identities')->count());
        $this->assertSame(1, DB::table('post_stats_daily')->count());

        // Idempotente y exitoso sin filas.
        Artisan::call('analytics:purge-events');
        $this->assertSame(0, Artisan::call('analytics:purge-events'));
    }

    public function test_the_days_option_changes_the_cutoff()
    {
        $productoId = $this->makeProduct();

        $this->seedClick($productoId, '2026-10-01 12:00:00');
        $this->seedClick($productoId, '2026-11-24 12:00:00');

        Artisan::call('analytics:purge-events', ['--days' => 30]);

        $remaining = DB::table('post_clicks')->get();
        $this->assertCount(1, $remaining);
        $this->assertSame('2026-11-24', substr((string) $remaining->first()->created_at, 0, 10));
    }

    public function test_opportunistic_purge_is_off_by_default()
    {
        config(['analytics.opportunistic_purge' => false]);

        $productoId = $this->makeProduct();
        $this->seedClick($productoId, '2026-07-28 12:00:00');

        $deleted = app(PostAnalyticsService::class)->purgeIfDue();

        $this->assertSame(0, $deleted);
        $this->assertSame(1, DB::table('post_clicks')->count());
        $this->assertNull(Cache::get('analytics:purge:last'));
    }

    public function test_opportunistic_purge_runs_once_per_day()
    {
        config(['analytics.opportunistic_purge' => true]);

        $productoId = $this->makeProduct();
        $this->seedClick($productoId, '2026-07-28 12:00:00');

        $first = app(PostAnalyticsService::class)->purgeIfDue();

        $this->assertSame(1, $first);
        $this->assertSame(0, DB::table('post_clicks')->count());
        $this->assertNotNull(Cache::get('analytics:purge:last'));

        $this->seedClick($productoId, '2026-07-29 12:00:00');

        $second = app(PostAnalyticsService::class)->purgeIfDue();

        $this->assertSame(0, $second);
        $this->assertSame(1, DB::table('post_clicks')->count());
    }

    public function test_opportunistic_purge_never_propagates_an_error()
    {
        config(['analytics.opportunistic_purge' => true]);

        Cache::shouldReceive('get')->andThrow(new \RuntimeException('cache down'));
        Log::spy();

        $this->assertSame(0, app(PostAnalyticsService::class)->purgeIfDue());

        Log::shouldHaveReceived('error')->withArgs(function ($message, $context) {
            return $message === 'analytics:purge_failed' && isset($context['error']);
        });
    }

    public function test_the_schedule_runs_the_purge_and_the_drift_check()
    {
        Artisan::call('schedule:list');

        $output = Artisan::output();

        $this->assertStringContainsString('analytics:purge-events', $output);
        $this->assertStringContainsString('analytics:check-drift', $output);
    }

    private function seedClick(int $productoId, string $createdAt)
    {
        DB::table('post_clicks')->insert([
            'producto_id' => $productoId,
            'date' => substr($createdAt, 0, 10),
            'identity' => 'clicker-'.uniqid(),
            'source' => 'web',
            'referer' => null,
            'user_agent' => null,
            'ip_hash' => str_repeat('a', 64),
            'created_at' => $createdAt,
        ]);
    }

    private function seedIdentity(int $productoId, string $createdAt)
    {
        DB::table('post_visit_identities')->insert([
            'producto_id' => $productoId,
            'date' => substr($createdAt, 0, 10),
            'identity' => 'visitor-'.uniqid(),
            'created_at' => $createdAt,
        ]);
    }
}
