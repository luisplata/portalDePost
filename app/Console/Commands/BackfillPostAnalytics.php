<?php

namespace App\Console\Commands;

use App\Services\AnalyticsTimeService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Backfill de visitas historicas desde `log_visits` (REQ-S3-08).
 *
 * Lee por chunks de PK, convierte UTC a fecha local en PHP (MySQL 5.7 sin
 * tablas de zona), agrega por producto y dia en memoria acotada y escribe con
 * `unique_visits = NULL` (sin captura de identidad). No toca `visit_posts` ni
 * `log_visits`, no crea indices y es idempotente/reejecutable.
 */
class BackfillPostAnalytics extends Command
{
    /**
     * @var string
     */
    protected $signature = 'analytics:backfill-visits
        {--from= : Fecha local Y-m-d de inicio}
        {--to= : Fecha local Y-m-d de fin}
        {--days= : Ventana en dias si falta from/to}
        {--from-id= : Id de log_visits para reanudar}';

    /**
     * @var string
     */
    protected $description = 'Reconstruye pageviews diarios de publicaciones desde log_visits';

    /**
     * @var int
     */
    private $rowsProcessed = 0;

    /**
     * @var array
     */
    private $daysRead = [];

    /**
     * @var array
     */
    private $daysOmitted = [];

    /**
     * @return int
     */
    public function handle()
    {
        // El comando se reutiliza entre llamadas dentro del proceso: el
        // resumen tiene que reflejar solo esta corrida.
        $this->rowsProcessed = 0;
        $this->daysRead = [];
        $this->daysOmitted = [];

        $time = app(AnalyticsTimeService::class);

        $days = $this->option('days');
        $days = ($days === null || $days === '')
            ? (int) config('analytics.backfill_days', 365)
            : (int) $days;

        $to = $this->option('to') ?: $time->today();
        $from = $this->option('from')
            ?: Carbon::parse($to)->subDays(max(1, $days) - 1)->toDateString();

        $range = $time->range($from, $to);
        $fromUtc = $time->dayStartUtc($range['from']);
        $toUtc = $time->dayEndUtc($range['to']);

        $lastId = (int) ($this->option('from-id') ?: 0);
        $buffer = [];
        $maxDate = null;
        $idleChunks = 0;

        while (true) {
            $rows = DB::table('log_visits')
                ->where('id', '>', $lastId)
                ->orderBy('id', 'asc')
                ->limit(1000)
                ->get(['id', 'producto_id', 'created_at']);

            if ($rows->isEmpty()) {
                break;
            }

            $beyondRange = true;

            foreach ($rows as $row) {
                $lastId = (int) $row->id;
                $createdAt = Carbon::parse((string) $row->created_at, 'UTC');

                if ($createdAt->lt($toUtc)) {
                    $beyondRange = false;
                }

                if ($createdAt->lt($fromUtc) || $createdAt->gte($toUtc)) {
                    continue;
                }

                $date = $time->toLocalDate($createdAt);
                $productoId = (int) $row->producto_id;

                if (! isset($buffer[$date][$productoId])) {
                    $buffer[$date][$productoId] = 0;
                }
                $buffer[$date][$productoId]++;

                if ($maxDate === null || $date > $maxDate) {
                    $maxDate = $date;
                }
            }

            // Margen de 1 dia por relojes desfasados.
            $this->flushBefore($buffer, $maxDate === null ? null : Carbon::parse($maxDate)->subDay()->toDateString());

            if ($rows->count() < 1000) {
                break;
            }

            if ($beyondRange) {
                $idleChunks++;
                if ($idleChunks >= 2) {
                    break;
                }
            } else {
                $idleChunks = 0;
            }
        }

        $this->flushBefore($buffer, null);

        $this->line('dias leidos: '.count($this->daysRead));
        $this->line('filas escritas: '.$this->rowsProcessed);
        $this->line('dias omitidos: '.count($this->daysOmitted));

        return 0;
    }

    /**
     * Vuelca los dias estrictamente anteriores al corte (o todos si es null).
     *
     * @param  array  $buffer
     * @param  string|null  $cutoff
     * @return void
     */
    private function flushBefore(array &$buffer, $cutoff)
    {
        foreach (array_keys($buffer) as $date) {
            if ($cutoff === null || $date < $cutoff) {
                $this->writeDay($date, $buffer[$date]);
                unset($buffer[$date]);
            }
        }
    }

    /**
     * Escribe un dia con set, no suma. Un dia con captura viva no se pisa.
     *
     * @param  string  $date
     * @param  array  $productCounts
     * @return void
     */
    private function writeDay($date, array $productCounts)
    {
        $this->daysRead[$date] = true;

        $hasLiveCapture = DB::table('post_stats_daily')
            ->where('date', $date)
            ->whereNotNull('unique_visits')
            ->exists();

        if ($hasLiveCapture) {
            $this->daysOmitted[$date] = true;

            // REQ-S3-08 scenario 3: un dia con captura viva se omite entero.
            return;
        }

        $now = Carbon::now('UTC')->toDateTimeString();

        foreach ($productCounts as $productoId => $visits) {
            DB::affectingStatement(
                'INSERT INTO post_stats_daily '
                .'(producto_id, date, visits, unique_visits, clicks, created_at, updated_at) '
                .'VALUES (?, ?, ?, NULL, 0, ?, ?) '
                .'ON DUPLICATE KEY UPDATE visits = IF(unique_visits IS NULL, VALUES(visits), visits)',
                [(int) $productoId, $date, (int) $visits, $now, $now]
            );

            $this->rowsProcessed++;
        }
    }
}
