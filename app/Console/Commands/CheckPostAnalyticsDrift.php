<?php

namespace App\Console\Commands;

use App\Services\AnalyticsTimeService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Compara los pageviews de la analitica contra log_visits (REQ-S3-10).
 *
 * Solo dias con captura viva (`unique_visits IS NOT NULL`); los dias
 * backfilleados salen de log_visits y compararlos seria tautologico. Nunca
 * escribe ni corrige datos.
 */
class CheckPostAnalyticsDrift extends Command
{
    /**
     * @var string
     */
    protected $signature = 'analytics:check-drift {--days=1 : Dias locales a revisar}';

    /**
     * @var string
     */
    protected $description = 'Reporta el desvio entre pageviews de analitica y log_visits';

    /**
     * @return int
     */
    public function handle()
    {
        $time = app(AnalyticsTimeService::class);

        $days = (int) ($this->option('days') ?: 1);
        $days = $days < 1 ? 1 : $days;

        $to = $time->today();
        $from = Carbon::parse($to)->subDays($days - 1)->toDateString();

        $threshold = (float) config('analytics.drift_threshold_pct', 5);
        $minVolume = (int) config('analytics.drift_min_volume', 50);

        foreach ($time->dates($from, $to) as $date) {
            $stats = DB::table('post_stats_daily')
                ->where('date', $date)
                ->whereNotNull('unique_visits')
                ->get(['producto_id', 'visits']);

            if ($stats->isEmpty()) {
                continue;
            }

            $legacy = DB::table('log_visits')
                ->where('created_at', '>=', $time->dayStartUtc($date)->toDateTimeString())
                ->where('created_at', '<', $time->dayEndUtc($date)->toDateTimeString())
                ->groupBy('producto_id')
                ->select('producto_id', DB::raw('COUNT(*) as total'))
                ->pluck('total', 'producto_id');

            foreach ($stats as $row) {
                $productoId = (int) $row->producto_id;
                $analytics = (int) $row->visits;
                $legacyCount = isset($legacy[$productoId]) ? (int) $legacy[$productoId] : 0;

                $denominator = max(1, $analytics, $legacyCount);
                $deviation = round(abs($analytics - $legacyCount) / $denominator * 100, 2);
                $volume = max($analytics, $legacyCount);

                $direction = 'equal';
                if ($analytics > $legacyCount) {
                    $direction = 'analytics_higher';
                } elseif ($legacyCount > $analytics) {
                    $direction = 'legacy_higher';
                }

                $this->line(
                    'producto '.$productoId.' '.$date
                    .' analitica='.$analytics
                    .' log_visits='.$legacyCount
                    .' desvio='.$deviation.'%'
                );

                if ($deviation > $threshold && $volume >= $minVolume) {
                    Log::warning('analytics:drift', [
                        'producto_id' => $productoId,
                        'date' => $date,
                        'analytics' => $analytics,
                        'legacy' => $legacyCount,
                        'deviation_pct' => $deviation,
                        'direction' => $direction,
                    ]);
                }
            }
        }

        return 0;
    }
}
