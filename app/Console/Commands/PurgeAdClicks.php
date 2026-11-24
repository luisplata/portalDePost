<?php

namespace App\Console\Commands;

use App\Services\AdMetricsService;
use Illuminate\Console\Command;

/**
 * Borra el detalle de clicks vencido (REQ-PRV-02).
 *
 * El corte se calcula en UTC sobre `created_at`; ad_stats_daily no se toca.
 * Idempotente y ejecutable a mano (el scheduler puede no correr en shared
 * hosting): sin nada que borrar, imprime 0 y sale con codigo 0.
 */
class PurgeAdClicks extends Command
{
    protected $signature = 'ads:purge-clicks {--days= : Dias de retencion (default config ads.retention_days)}';

    protected $description = 'Borra clicks de publicidad vencidos sin tocar los agregados diarios';

    /**
     * @param  \App\Services\AdMetricsService  $metrics
     * @return int
     */
    public function handle(AdMetricsService $metrics)
    {
        $days = $this->option('days');
        $days = (int) ($days === null || $days === '' ? config('ads.retention_days', 90) : $days);

        $deleted = $metrics->purge($days);

        $this->info('Deleted '.$deleted.' clicks older than '.$days.' days');

        return 0;
    }
}
