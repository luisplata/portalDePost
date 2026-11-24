<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

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

    /**
     * Rango local validado [from, to] en Y-m-d (REQ-MET-02).
     *
     * Defaults: hoy y hoy - 29 (30 dias inclusivos). Fecha invalida o rango
     * invertido => InvalidArgumentException('invalid_range'). Un span mayor a
     * 365 dias se recorta a los ultimos 365, sin error.
     *
     * @param  mixed  $from
     * @param  mixed  $to
     * @return array
     */
    public function range($from = null, $to = null)
    {
        $timezone = (string) config('ads.timezone', 'America/Bogota');
        $today = Carbon::now($timezone);

        $to = $this->parseDay($to, $today->toDateString());
        $from = $this->parseDay($from, $today->copy()->subDays(29)->toDateString());

        if ($from->greaterThan($to)) {
            throw new InvalidArgumentException('invalid_range');
        }

        if ($from->diffInDays($to) > 364) {
            $from = $to->copy()->subDays(364);
        }

        return [$from->toDateString(), $to->toDateString()];
    }

    /**
     * Serie diaria con TODOS los dias del rango (REQ-MET-03).
     *
     * Una sola query agregada GROUP BY date; el relleno de dias sin datos se
     * hace en PHP para no dejar huecos en las graficas.
     *
     * @param  string  $from  Y-m-d
     * @param  string  $to  Y-m-d
     * @return array
     */
    public function series($from, $to)
    {
        $rows = DB::table('ad_stats_daily')
            ->whereBetween('date', [$from, $to])
            ->groupBy('date')
            ->select(
                'date',
                DB::raw('SUM(impressions) as impressions'),
                DB::raw('SUM(clicks) as clicks')
            )
            ->get()
            ->keyBy(function ($row) {
                return substr((string) $row->date, 0, 10);
            });

        $series = [];
        $cursor = Carbon::parse($from);
        $end = Carbon::parse($to);

        while ($cursor->lessThanOrEqualTo($end)) {
            $date = $cursor->toDateString();
            $row = $rows->get($date);
            $impressions = $row === null ? 0 : (int) $row->impressions;
            $clicks = $row === null ? 0 : (int) $row->clicks;

            $series[] = [
                'date' => $date,
                'impressions' => $impressions,
                'clicks' => $clicks,
                'ctr' => $this->ctr($clicks, $impressions),
            ];

            $cursor->addDay();
        }

        return $series;
    }

    /**
     * Totales del rango como suma de la serie (sin otra query).
     *
     * @param  array  $series
     * @return array
     */
    public function totals(array $series)
    {
        $impressions = 0;
        $clicks = 0;

        foreach ($series as $day) {
            $impressions += (int) $day['impressions'];
            $clicks += (int) $day['clicks'];
        }

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $this->ctr($clicks, $impressions),
        ];
    }

    /**
     * Borra ad_clicks mas viejo que la retencion, en lotes de 1000 y en UTC
     * (REQ-PRV-02). No toca ad_stats_daily. Devuelve las filas borradas.
     *
     * @param  int|null  $days
     * @return int
     */
    public function purge($days = null)
    {
        $days = (int) ($days === null || $days === '' ? config('ads.retention_days', 90) : $days);
        $days = $days < 1 ? 1 : $days;
        $cutoff = Carbon::now('UTC')->subDays($days);

        $total = 0;

        do {
            $deleted = DB::table('ad_clicks')
                ->where('created_at', '<', $cutoff)
                ->limit(1000)
                ->delete();
            $total += $deleted;
        } while ($deleted > 0);

        return $total;
    }

    /**
     * Purga oportunista: como maximo una vez cada 24 h (REQ-PRV-03).
     * Cualquier falla se loguea y nunca se propaga a la respuesta.
     *
     * @return int
     */
    public function purgeIfDue()
    {
        if (Cache::get('ads:purge:last') !== null) {
            return 0;
        }

        try {
            $deleted = $this->purge();
            Cache::put('ads:purge:last', Carbon::now('UTC')->toIso8601String(), Carbon::now()->addDay());

            return $deleted;
        } catch (\Throwable $e) {
            Log::error('ads:purge-clicks oportunista fallo: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * @param  mixed  $value
     * @param  string  $default  Y-m-d
     * @return \Illuminate\Support\Carbon
     */
    private function parseDay($value, $default)
    {
        if ($value === null || $value === '') {
            return Carbon::parse($default);
        }

        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException('invalid_range');
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('invalid_range');
        }

        // createFromFormat desborda fechas imposibles (2026-02-30 => marzo).
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('invalid_range');
        }

        return $date;
    }
}
