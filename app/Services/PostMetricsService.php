<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Lectura agregada de analitica de publicaciones.
 *
 * Seis sentencias constantes como maximo (cobertura, serie, universo+hechos,
 * UV-r, UC-r/CV, legacy) y ensamblado en PHP. MySQL 5.7: sin window functions
 * ni CTE. Los dias se calculan con AnalyticsTimeService.
 *
 * Regla de honestidad: si la cobertura no alcanza, el numero va null con un
 * warning; nunca un aproximado silencioso. `unique_visits` nulo en un dia es
 * la marca de "sin captura de identidad" (DD-09).
 */
class PostMetricsService
{
    /**
     * @var AnalyticsTimeService
     */
    protected $time;

    public function __construct(AnalyticsTimeService $time)
    {
        $this->time = $time;
    }

    /**
     * Payload completo del panel.
     *
     * @param  array  $query  from, to, metric, include_inactive, page, per_page, range_truncated
     * @return array
     */
    public function metrics(array $query = [])
    {
        $from = $query['from'];
        $to = $query['to'];
        $requestedMetric = isset($query['metric']) && $query['metric'] !== '' ? $query['metric'] : 'uv';
        $page = isset($query['page']) ? (int) $query['page'] : 1;
        $perPage = isset($query['per_page']) ? (int) $query['per_page'] : 10;
        $includeInactive = isset($query['include_inactive'])
            && (string) $query['include_inactive'] === '1';

        $coverage = $this->coverage();

        $visitsFrom = $coverage['visits_from'];
        $identitiesFrom = $coverage['identities_from'];
        $clicksFrom = $coverage['clicks_from'];

        $identitiesFull = $identitiesFrom !== null && $identitiesFrom <= $from;
        $identitiesPartial = ! $identitiesFull && $identitiesFrom !== null && $identitiesFrom <= $to;
        $identitiesUnavailable = ! $identitiesFull && ! $identitiesPartial;

        $clicksFull = $clicksFrom !== null && $clicksFrom <= $from;
        $clicksPartial = ! $clicksFull && $clicksFrom !== null && $clicksFrom <= $to;
        $clicksUnavailable = ! $clicksFull && ! $clicksPartial;

        $conversionAvailable = $identitiesFull && $clicksFull;

        $metric = $requestedMetric;
        if ($metric === 'uv' && $identitiesUnavailable) {
            $metric = 'pageviews';
        }

        $uvBoundary = $identitiesFull ? $from : ($identitiesPartial ? $identitiesFrom : '9999-12-31');
        $clicksBoundary = $clicksFull ? $from : ($clicksPartial ? $clicksFrom : '9999-12-31');

        // 2. Serie global del rango.
        $seriesRows = DB::table('post_stats_daily')
            ->whereBetween('date', [$from, $to])
            ->groupBy('date')
            ->select(
                'date',
                DB::raw('SUM(visits) as visits'),
                DB::raw('SUM(unique_visits) as uv_daily'),
                DB::raw('SUM(clicks) as clicks')
            )
            ->get()
            ->keyBy(function ($row) {
                return substr((string) $row->date, 0, 10);
            });

        // 3. Universo + hechos por producto. LEFT JOIN: los sin datos van en 0.
        $universeQuery = DB::table('productos')
            ->leftJoin('post_stats_daily as s', function ($join) use ($from, $to) {
                $join->on('s.producto_id', '=', 'productos.id')
                    ->whereBetween('s.date', [$from, $to]);
            })
            ->groupBy('productos.id', 'productos.nombre', 'productos.estado')
            ->orderBy('productos.id', 'asc')
            ->select(
                'productos.id',
                'productos.nombre',
                'productos.estado',
                DB::raw('COALESCE(SUM(s.visits), 0) as visits'),
                DB::raw('SUM(CASE WHEN s.date >= ? THEN COALESCE(s.unique_visits, 0) ELSE 0 END) as uv_daily'),
                DB::raw('COALESCE(SUM(s.clicks), 0) as clicks_total'),
                DB::raw('SUM(CASE WHEN s.date >= ? THEN s.clicks ELSE 0 END) as clicks_window')
            )
            ->setBindings([$uvBoundary, $clicksBoundary], 'select');

        if (! $includeInactive) {
            $universeQuery->where('productos.estado', 1)
                ->where('productos.publication_date', '<', now());
        }

        $universe = $universeQuery->get();

        // 4. UV-r exacto por producto (solo con cobertura completa de identidades).
        $uvExact = [];
        if ($identitiesFull) {
            $uvExact = DB::table('post_visit_identities')
                ->whereBetween('date', [$from, $to])
                ->groupBy('producto_id')
                ->select('producto_id', DB::raw('COUNT(DISTINCT identity) as uv'))
                ->get()
                ->pluck('uv', 'producto_id')
                ->map(function ($value) {
                    return (int) $value;
                })
                ->all();
        }

        // 5. UC-r y CV por producto (solo con cobertura completa de clicks).
        $clickers = [];
        if ($clicksFull) {
            $clickers = DB::table('post_clicks as c')
                ->leftJoin('post_visit_identities as v', function ($join) use ($from, $to) {
                    $join->on('v.producto_id', '=', 'c.producto_id')
                        ->on('v.identity', '=', 'c.identity')
                        ->whereBetween('v.date', [$from, $to]);
                })
                ->whereBetween('c.date', [$from, $to])
                ->groupBy('c.producto_id')
                ->select(
                    'c.producto_id',
                    DB::raw('COUNT(DISTINCT c.identity) as uc'),
                    DB::raw('COUNT(DISTINCT v.identity) as cv')
                )
                ->get()
                ->keyBy('producto_id');
        }

        // 6. Legacy: SUM por producto antes de totalizar, para tolerar filas
        // duplicadas de visit_posts. Nunca hasOne ni $producto->Visitas.
        $legacyPerProduct = DB::table('visit_posts')
            ->groupBy('producto_id')
            ->select(
                'producto_id',
                DB::raw('SUM(visita) as visita'),
                DB::raw('SUM(idoAlPack) as idoAlPack')
            );

        $legacyRow = DB::query()
            ->fromSub($legacyPerProduct, 'vp')
            ->selectRaw('COALESCE(SUM(visita), 0) as visits, COALESCE(SUM(idoAlPack), 0) as clicks')
            ->first();

        $series = $this->buildSeries(
            $from,
            $to,
            $seriesRows,
            $visitsFrom,
            $identitiesFrom,
            $identitiesPartial,
            $identitiesUnavailable,
            $clicksFrom,
            $clicksPartial,
            $clicksUnavailable
        );

        $rankingData = $this->buildRanking(
            $universe,
            $uvExact,
            $clickers,
            $metric,
            $identitiesUnavailable,
            $identitiesFull,
            $clicksUnavailable,
            $clicksFull,
            $conversionAvailable
        );

        $rankingData = $this->sortRanking($rankingData, $metric);

        $total = count($rankingData);
        $perPage = min(50, max(1, $perPage));
        $page = max(1, $page);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $data = array_slice($rankingData, ($page - 1) * $perPage, $perPage);

        $totals = $this->buildTotals(
            $series,
            $rankingData,
            $uvExact,
            $clickers,
            $visitsFrom,
            $identitiesUnavailable,
            $identitiesFull,
            $clicksUnavailable,
            $clicksFull,
            $conversionAvailable
        );

        $legacyVisits = (int) $legacyRow->visits;
        $legacyClicks = (int) $legacyRow->clicks;

        return [
            'from' => $from,
            'to' => $to,
            'timezone' => $this->time->timezone(),
            'metric' => $metric,
            'coverage' => $coverage,
            'uv_approximate' => $identitiesPartial,
            'conversion_available' => $conversionAvailable,
            'series' => $series,
            'totals' => $totals,
            'legacy' => [
                'scope' => 'all_time',
                'visits' => $legacyVisits,
                'clicks' => $legacyClicks,
                'conversion_by_pageview' => $this->conversionByPageview($legacyClicks, $legacyVisits),
            ],
            'ranking' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'data' => $data,
            ],
            'warnings' => $this->warnings(
                $seriesRows,
                $identitiesUnavailable,
                $identitiesPartial,
                $clicksUnavailable,
                $conversionAvailable,
                ! empty($query['range_truncated'])
            ),
        ];
    }

    /**
     * Cobertura global de los tres almacenes.
     *
     * @return array
     */
    public function coverage()
    {
        $row = DB::table('post_stats_daily')
            ->selectRaw('MIN(date) as visits_from')
            ->selectRaw('MIN(CASE WHEN unique_visits IS NOT NULL THEN date END) as identities_from')
            ->selectRaw('MIN(CASE WHEN clicks > 0 THEN date END) as clicks_from')
            ->first();

        return [
            'visits_from' => $this->day($row ? $row->visits_from : null),
            'identities_from' => $this->day($row ? $row->identities_from : null),
            'clicks_from' => $this->day($row ? $row->clicks_from : null),
        ];
    }

    /**
     * Rango local normalizado con default de 30 dias y recorte a 365.
     *
     * @param  string|null  $from
     * @param  string|null  $to
     * @return array
     */
    public function range($from = null, $to = null)
    {
        return $this->time->range($from, $to);
    }

    /**
     * @param  int|string|null  $cv
     * @param  int|string|null  $uv
     * @return float|null
     */
    public function conversionPrincipal($cv, $uv)
    {
        if ($cv === null || $uv === null || (int) $uv <= 0) {
            return null;
        }

        $value = ((int) $cv / (int) $uv) * 100;

        return round(min(100, max(0, $value)), 2);
    }

    /**
     * @param  int|string|null  $clicks
     * @param  int|string|null  $visits
     * @return float|null
     */
    public function conversionByPageview($clicks, $visits)
    {
        if ($clicks === null || $visits === null || (int) $visits <= 0) {
            return null;
        }

        return round(((int) $clicks / (int) $visits) * 100, 2);
    }

    /**
     * @param  int|string|null  $clicks
     * @param  int|string|null  $uv
     * @return float|null
     */
    public function clicksPerVisitor($clicks, $uv)
    {
        if ($clicks === null || $uv === null || (int) $uv <= 0) {
            return null;
        }

        return round((int) $clicks / (int) $uv, 2);
    }

    /**
     * Serie completa del rango con nulls por cobertura.
     */
    private function buildSeries(
        $from,
        $to,
        $rows,
        $visitsFrom,
        $identitiesFrom,
        $identitiesPartial,
        $identitiesUnavailable,
        $clicksFrom,
        $clicksPartial,
        $clicksUnavailable
    ) {
        $series = [];

        foreach ($this->time->dates($from, $to) as $date) {
            $row = $rows->get($date);

            $visits = null;
            if ($visitsFrom !== null && $date >= $visitsFrom) {
                $visits = $row === null ? 0 : (int) $row->visits;
            }

            $uniqueVisits = null;
            if (! $identitiesUnavailable) {
                if (! $identitiesPartial || $date >= $identitiesFrom) {
                    $uniqueVisits = $row === null ? 0 : (int) $row->uv_daily;
                }
            }

            $clicks = null;
            if (! $clicksUnavailable) {
                if (! $clicksPartial || $date >= $clicksFrom) {
                    $clicks = $row === null ? 0 : (int) $row->clicks;
                }
            }

            $series[] = [
                'date' => $date,
                'visits' => $visits,
                'unique_visits' => $uniqueVisits,
                'clicks' => $clicks,
            ];
        }

        return $series;
    }

    /**
     * Filas del ranking con los nulls de cobertura aplicados.
     */
    private function buildRanking(
        $universe,
        array $uvExact,
        $clickers,
        $metric,
        $identitiesUnavailable,
        $identitiesFull,
        $clicksUnavailable,
        $clicksFull,
        $conversionAvailable
    ) {
        $data = [];

        foreach ($universe as $row) {
            $productoId = (int) $row->id;
            $visits = (int) $row->visits;

            $uniqueVisits = null;
            if (! $identitiesUnavailable) {
                $uniqueVisits = $identitiesFull
                    ? (isset($uvExact[$productoId]) ? $uvExact[$productoId] : 0)
                    : (int) $row->uv_daily;
            }

            $clicks = null;
            if (! $clicksUnavailable) {
                $clicks = $clicksFull ? (int) $row->clicks_total : (int) $row->clicks_window;
            }

            $uniqueClickers = null;
            $convertedVisitors = null;
            if ($clickers !== null && isset($clickers[$productoId])) {
                $uniqueClickers = (int) $clickers[$productoId]->uc;
                $convertedVisitors = (int) $clickers[$productoId]->cv;
            } elseif ($clicksFull) {
                $uniqueClickers = 0;
                $convertedVisitors = 0;
            }

            $data[] = [
                'producto_id' => $productoId,
                'nombre' => $row->nombre,
                'slug' => str_replace(' ', '-', $row->nombre),
                'estado' => (int) $row->estado,
                'visits' => $visits,
                'unique_visits' => $uniqueVisits,
                'unique_clickers' => $uniqueClickers,
                'converted_visitors' => $convertedVisitors,
                'clicks' => $clicks,
                'conversion_principal' => $conversionAvailable
                    ? $this->conversionPrincipal($convertedVisitors, $uniqueVisits)
                    : null,
                'conversion_by_pageview' => $clicksFull
                    ? $this->conversionByPageview($clicks, $visits)
                    : null,
                'clicks_per_visitor' => $this->clicksPerVisitor($clicks, $uniqueVisits),
            ];
        }

        return $data;
    }

    /**
     * Orden del ranking: metrica efectiva, clicks y producto_id ascendente.
     */
    private function sortRanking(array $data, $metric)
    {
        usort($data, function ($a, $b) use ($metric) {
            $left = isset($a[$metric]) && $a[$metric] !== null ? $a[$metric] : -1;
            $right = isset($b[$metric]) && $b[$metric] !== null ? $b[$metric] : -1;

            if ($left !== $right) {
                return $right <=> $left;
            }

            $leftClicks = $a['clicks'] === null ? -1 : $a['clicks'];
            $rightClicks = $b['clicks'] === null ? -1 : $b['clicks'];

            if ($leftClicks !== $rightClicks) {
                return $rightClicks <=> $leftClicks;
            }

            return $a['producto_id'] <=> $b['producto_id'];
        });

        return $data;
    }

    /**
     * Totales del rango: suman solo los dias cubiertos.
     */
    private function buildTotals(
        array $series,
        array $ranking,
        array $uvExact,
        $clickers,
        $visitsFrom,
        $identitiesUnavailable,
        $identitiesFull,
        $clicksUnavailable,
        $clicksFull,
        $conversionAvailable
    ) {
        $visits = null;
        if ($visitsFrom !== null && $visitsFrom <= end($series)['date']) {
            $visits = 0;
            foreach ($series as $day) {
                if ($day['visits'] !== null) {
                    $visits += (int) $day['visits'];
                }
            }
        }

        $uniqueVisits = null;
        if (! $identitiesUnavailable) {
            if ($identitiesFull) {
                $uniqueVisits = array_sum($uvExact);
            } else {
                $uniqueVisits = 0;
                foreach ($series as $day) {
                    if ($day['unique_visits'] !== null) {
                        $uniqueVisits += (int) $day['unique_visits'];
                    }
                }
            }
        }

        $clicks = null;
        if (! $clicksUnavailable) {
            $clicks = 0;
            foreach ($series as $day) {
                if ($day['clicks'] !== null) {
                    $clicks += (int) $day['clicks'];
                }
            }
        }

        $uniqueClickers = null;
        $convertedVisitors = null;
        if ($clicksFull && $clickers !== null) {
            $uniqueClickers = 0;
            $convertedVisitors = 0;
            foreach ($clickers as $row) {
                $uniqueClickers += (int) $row->uc;
                $convertedVisitors += (int) $row->cv;
            }
        }

        return [
            'visits' => $visits,
            'unique_visits' => $uniqueVisits,
            'unique_clickers' => $uniqueClickers,
            'converted_visitors' => $convertedVisitors,
            'clicks' => $clicks,
            'conversion_principal' => $conversionAvailable
                ? $this->conversionPrincipal($convertedVisitors, $uniqueVisits)
                : null,
            'conversion_by_pageview' => $clicksFull
                ? $this->conversionByPageview($clicks, $visits)
                : null,
            'clicks_per_visitor' => $this->clicksPerVisitor($clicks, $uniqueVisits),
        ];
    }

    /**
     * Warnings del conjunto cerrado, sin duplicados.
     */
    private function warnings(
        $seriesRows,
        $identitiesUnavailable,
        $identitiesPartial,
        $clicksUnavailable,
        $conversionAvailable,
        $rangeTruncated
    ) {
        $warnings = [];

        if (count($seriesRows) === 0) {
            $warnings[] = 'no_data';
        }
        if ($identitiesUnavailable) {
            $warnings[] = 'uv_unavailable';
        }
        if ($identitiesPartial) {
            $warnings[] = 'uv_approximate';
        }
        if ($clicksUnavailable) {
            $warnings[] = 'clicks_unavailable';
        }
        if (! $conversionAvailable) {
            $warnings[] = 'conversion_unavailable';
        }
        if ($rangeTruncated) {
            $warnings[] = 'range_truncated';
        }

        return array_values(array_unique($warnings));
    }

    /**
     * @param  mixed  $value
     * @return string|null
     */
    private function day($value)
    {
        return $value === null || $value === '' ? null : substr((string) $value, 0, 10);
    }
}
