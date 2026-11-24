<?php

namespace App\Console\Commands;

use App\Services\PostAnalyticsService;
use Illuminate\Console\Command;

/**
 * Borra eventos crudos de analitica mas viejos que la retencion (REQ-S3-09).
 *
 * En lotes de 1000 y por `created_at` UTC. No toca los agregados diarios.
 */
class PurgePostAnalytics extends Command
{
    /**
     * @var string
     */
    protected $signature = 'analytics:purge-events {--days= : Dias de retencion}';

    /**
     * @var string
     */
    protected $description = 'Purga clicks e identidades de visita vencidos';

    /**
     * @return int
     */
    public function handle()
    {
        $deleted = app(PostAnalyticsService::class)->purgeEvents($this->option('days'));

        $this->line('clicks borrados: '.$deleted['post_clicks']);
        $this->line('identidades borradas: '.$deleted['post_visit_identities']);

        return 0;
    }
}
