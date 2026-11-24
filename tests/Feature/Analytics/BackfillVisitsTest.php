<?php

namespace Tests\Feature\Analytics;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Backfill de visitas historicas desde log_visits (T-S3c-01..03, REQ-S3-08).
 *
 * Convierte UTC a fecha local en PHP, escribe `unique_visits = NULL` (sin
 * captura de identidad), no pisa dias con captura viva y es reejecutable.
 */
class BackfillVisitsTest extends AnalyticsTestSupport
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase puede dejar un binding mockeado de OutputStyle en el
        // contenedor; se elimina para capturar la salida real de los comandos.
        $this->app->offsetUnset(\Illuminate\Console\OutputStyle::class);
    }

    public function test_it_converts_utc_to_the_local_day_and_leaves_uv_null()
    {
        $productoId = $this->makeProduct(['nombre' => 'Backfill Uno']);

        $this->makeLogVisitAt($productoId, '2026-11-10 03:00:00');
        $this->makeLogVisitAt($productoId, '2026-11-10 04:30:00');

        $exit = Artisan::call('analytics:backfill-visits', [
            '--from' => '2026-11-09',
            '--to' => '2026-11-09',
        ]);

        $this->assertSame(0, $exit);

        $stats = DB::table('post_stats_daily')->where('producto_id', $productoId)->first();

        $this->assertNotNull($stats);
        $this->assertSame('2026-11-09', $stats->date);
        $this->assertSame(2, (int) $stats->visits);
        $this->assertNull($stats->unique_visits);
        $this->assertSame(0, (int) $stats->clicks);

        // No toca las tablas legacy.
        $this->assertSame(0, DB::table('visit_posts')->count());
    }

    public function test_running_it_twice_does_not_change_values_or_the_summary()
    {
        $productoId = $this->makeProduct(['nombre' => 'Backfill Idempotente']);
        $this->makeLogVisitAt($productoId, '2026-11-10 03:00:00');

        Artisan::call('analytics:backfill-visits', ['--from' => '2026-11-09', '--to' => '2026-11-09']);
        $firstSummary = Artisan::output();

        Artisan::call('analytics:backfill-visits', ['--from' => '2026-11-09', '--to' => '2026-11-09']);
        $secondSummary = Artisan::output();

        $stats = DB::table('post_stats_daily')->where('producto_id', $productoId)->get();

        $this->assertCount(1, $stats);
        $this->assertSame(1, (int) $stats->first()->visits);
        $this->assertSame($firstSummary, $secondSummary);
        $this->assertStringContainsString('dias leidos: 1', $firstSummary);
        $this->assertStringContainsString('filas escritas: 1', $firstSummary);
    }

    public function test_it_does_not_overwrite_a_day_with_live_identity_capture()
    {
        $productoId = $this->makeProduct(['nombre' => 'Backfill Vivo']);

        DB::table('post_stats_daily')->insert([
            'producto_id' => $productoId,
            'date' => '2026-11-09',
            'visits' => 99,
            'unique_visits' => 7,
            'clicks' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->makeLogVisitAt($productoId, '2026-11-10 03:00:00');

        Artisan::call('analytics:backfill-visits', ['--from' => '2026-11-09', '--to' => '2026-11-09']);

        $stats = DB::table('post_stats_daily')->where('producto_id', $productoId)->first();

        $this->assertSame(99, (int) $stats->visits);
        $this->assertSame(7, (int) $stats->unique_visits);
        $this->assertSame(3, (int) $stats->clicks);
        $this->assertStringContainsString('dias omitidos: 1', Artisan::output());
    }

    public function test_it_succeeds_without_data_and_respects_the_range()
    {
        $productoId = $this->makeProduct(['nombre' => 'Backfill Rango']);
        $this->makeLogVisitAt($productoId, '2026-11-20 03:00:00');

        $exit = Artisan::call('analytics:backfill-visits', [
            '--from' => '2026-11-01',
            '--to' => '2026-11-02',
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame(0, DB::table('post_stats_daily')->count());
        $this->assertStringContainsString('filas escritas: 0', Artisan::output());
    }

    public function test_from_id_lets_the_backfill_resume()
    {
        $productoId = $this->makeProduct(['nombre' => 'Backfill Reanuda']);

        $firstId = $this->makeLogVisitAt($productoId, '2026-11-10 03:00:00');
        $this->makeLogVisitAt($productoId, '2026-11-10 04:00:00');

        Artisan::call('analytics:backfill-visits', [
            '--from' => '2026-11-09',
            '--to' => '2026-11-09',
            '--from-id' => $firstId,
        ]);

        $stats = DB::table('post_stats_daily')->where('producto_id', $productoId)->first();

        $this->assertSame(1, (int) $stats->visits);
    }

    public function test_it_processes_more_than_one_chunk()
    {
        $productoId = $this->makeProduct(['nombre' => 'Backfill Chunks']);

        $rows = [];
        foreach (range(1, 1005) as $i) {
            $rows[] = [
                'producto_id' => $productoId,
                'created_at' => '2026-11-10 03:00:00',
                'updated_at' => '2026-11-10 03:00:00',
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('log_visits')->insert($chunk);
        }

        Artisan::call('analytics:backfill-visits', [
            '--from' => '2026-11-09',
            '--to' => '2026-11-09',
        ]);

        $stats = DB::table('post_stats_daily')->where('producto_id', $productoId)->first();

        $this->assertSame(1005, (int) $stats->visits);
    }

    private function makeLogVisitAt(int $productoId, string $createdAt): int
    {
        return DB::table('log_visits')->insertGetId([
            'producto_id' => $productoId,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
