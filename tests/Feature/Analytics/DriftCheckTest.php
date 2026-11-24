<?php

namespace Tests\Feature\Analytics;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Chequeo de drift entre la analitica y log_visits (T-S3d-03/04, REQ-S3-10).
 *
 * Solo dias con captura viva (`unique_visits IS NOT NULL`). Nunca escribe:
 * solo reporta y loguea cuando el desvio supera el 5 % con volumen >= 50.
 */
class DriftCheckTest extends AnalyticsTestSupport
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase puede dejar un binding mockeado de OutputStyle en el
        // contenedor; se elimina para capturar la salida real de los comandos.
        $this->app->offsetUnset(\Illuminate\Console\OutputStyle::class);


        // 02:00 UTC del 25 = 21:00 local del 24.
        Carbon::setTestNow('2026-11-25 02:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_deviation_below_the_threshold_does_not_warn()
    {
        $productoId = $this->seedScenario(100, 104);

        Log::spy();

        Artisan::call('analytics:check-drift', ['--days' => 1]);

        $output = Artisan::output();
        $this->assertStringContainsString('100', $output);
        $this->assertStringContainsString('104', $output);
        $this->assertStringContainsString('3.85', $output);

        Log::shouldNotHaveReceived('warning');

        $this->assertSame(100, (int) DB::table('post_stats_daily')->where('producto_id', $productoId)->value('visits'));
    }

    public function test_a_deviation_above_the_threshold_logs_the_direction()
    {
        $productoId = $this->seedScenario(100, 120);

        Log::spy();

        Artisan::call('analytics:check-drift', ['--days' => 1]);

        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) use ($productoId) {
            return $message === 'analytics:drift'
                && $context['producto_id'] === $productoId
                && $context['date'] === '2026-11-24'
                && $context['analytics'] === 100
                && $context['legacy'] === 120
                && $context['direction'] === 'legacy_higher';
        });

        // Nunca escribe.
        $this->assertSame(100, (int) DB::table('post_stats_daily')->where('producto_id', $productoId)->value('visits'));
        $this->assertSame(120, DB::table('log_visits')->where('producto_id', $productoId)->count());
    }

    public function test_a_small_volume_does_not_warn_even_with_a_large_deviation()
    {
        $this->seedScenario(3, 10);

        Log::spy();

        Artisan::call('analytics:check-drift', ['--days' => 1]);

        Log::shouldNotHaveReceived('warning');
    }

    public function test_backfilled_days_are_skipped()
    {
        $productoId = $this->makeProduct(['nombre' => 'Drift Backfill']);

        DB::table('post_stats_daily')->insert([
            'producto_id' => $productoId,
            'date' => '2026-11-24',
            'visits' => 50,
            'unique_visits' => null,
            'clicks' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedLogVisits($productoId, 100);

        Log::spy();

        Artisan::call('analytics:check-drift', ['--days' => 1]);

        Log::shouldNotHaveReceived('warning');
        $this->assertStringNotContainsString('producto '.$productoId, Artisan::output());
    }

    /**
     * Siembra un dia local completo con A pageviews analiticos y B log_visits.
     */
    private function seedScenario(int $analyticsVisits, int $legacyVisits): int
    {
        $productoId = $this->makeProduct(['nombre' => 'Drift '.$analyticsVisits]);

        DB::table('post_stats_daily')->insert([
            'producto_id' => $productoId,
            'date' => '2026-11-24',
            'visits' => $analyticsVisits,
            'unique_visits' => min(10, $analyticsVisits),
            'clicks' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedLogVisits($productoId, $legacyVisits);

        return $productoId;
    }

    private function seedLogVisits(int $productoId, int $count)
    {
        $rows = [];
        foreach (range(1, $count) as $i) {
            $rows[] = [
                'producto_id' => $productoId,
                'created_at' => '2026-11-24 15:00:00',
                'updated_at' => '2026-11-24 15:00:00',
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('log_visits')->insert($chunk);
        }
    }
}
