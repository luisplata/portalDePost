<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agregacion de metricas de publicidad (REQ-ADM-06, REQ-MET-04).
 *
 * El dia operativo es el dia LOCAL de config('ads.timezone'): `ad_stats_daily.date`
 * ya es una fecha local, asi que se compara sin conversion (E-1).
 */
class AdMetricsService
{
    /**
     * CTR en porcentaje con 2 decimales; sin impresiones es 0 (REQ-MET-04).
     *
     * @param  int  $clicks
     * @param  int  $impressions
     * @return float
     */
    public function ctr($clicks, $impressions)
    {
        if ((int) $impressions <= 0) {
            return 0.0;
        }

        return round(((int) $clicks / (int) $impressions) * 100, 2);
    }

    /**
     * Ventana de los ultimos N dias en fecha local: [from, to] Y-m-d.
     *
     * @param  int  $days
     * @return array
     */
    public function lastDays($days = 30)
    {
        $timezone = (string) config('ads.timezone', 'America/Bogota');
        $to = Carbon::now($timezone);

        return [$to->copy()->subDays($days - 1)->toDateString(), $to->toDateString()];
    }

    /**
     * Impresiones, clicks y CTR por creatividad en el rango local.
     *
     * Una sola query agregada para todo el listado (sin N+1); solo devuelve las
     * creatividades con filas en el rango, el llamador completa con ceros.
     *
     * @param  array  $creativeIds
     * @param  string  $from  Y-m-d
     * @param  string  $to  Y-m-d
     * @return array  id => ['impressions' => int, 'clicks' => int, 'ctr' => float]
     */
    public function summaryForCreatives(array $creativeIds, $from, $to)
    {
        if (empty($creativeIds)) {
            return [];
        }

        $rows = DB::table('ad_stats_daily')
            ->whereIn('creative_id', $creativeIds)
            ->whereBetween('date', [$from, $to])
            ->groupBy('creative_id')
            ->select(
                'creative_id',
                DB::raw('SUM(impressions) as impressions'),
                DB::raw('SUM(clicks) as clicks')
            )
            ->get();

        $summary = [];

        foreach ($rows as $row) {
            $impressions = (int) $row->impressions;
            $clicks = (int) $row->clicks;

            $summary[(int) $row->creative_id] = [
                'impressions' => $impressions,
                'clicks' => $clicks,
                'ctr' => $this->ctr($clicks, $impressions),
            ];
        }

        return $summary;
    }
}
