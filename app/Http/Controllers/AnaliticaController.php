<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsTimeService;
use App\Services\PostMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Metricas de analitica de publicaciones para el admin (REQ-S3-06, REQ-S4-07).
 *
 * Solo lectura: GET con la sesion existente. El rango entra y sale como fecha
 * local (America/Bogota) y no se convierte. Cualquier falla del servicio se
 * responde una sola vez con 500 `metrics_unavailable`.
 */
class AnaliticaController extends Controller
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function metricas(Request $request)
    {
        try {
            $range = $this->validatedRange($request);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'invalid_range'], 422);
        }

        $metric = $request->query('metric', 'uv');
        if (! in_array($metric, ['uv', 'pageviews'], true)) {
            return response()->json(['error' => 'invalid_metric'], 422);
        }

        try {
            $payload = app(PostMetricsService::class)->metrics([
                'from' => $range['from'],
                'to' => $range['to'],
                'metric' => $metric,
                'include_inactive' => $request->query('include_inactive'),
                'page' => $this->positiveInt($request->query('page'), 1),
                'per_page' => $this->perPage($request->query('per_page')),
                'range_truncated' => $range['truncated'],
            ]);
        } catch (\Throwable $e) {
            Log::error('analytics:metrics_failed', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'metrics_unavailable'], 500);
        }

        return response()->json($payload, 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * Valida y normaliza el rango local.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    private function validatedRange(Request $request)
    {
        $time = app(AnalyticsTimeService::class);

        $to = $request->query('to');
        $from = $request->query('from');

        $to = ($to === null || $to === '')
            ? $time->today()
            : $this->parseDate($to);

        $from = ($from === null || $from === '')
            ? Carbon::parse($to)->subDays(29)->toDateString()
            : $this->parseDate($from);

        if ($from > $to) {
            throw new InvalidArgumentException('invalid_range');
        }

        $truncated = Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 364;

        $range = $time->range($from, $to);

        return [
            'from' => $range['from'],
            'to' => $range['to'],
            'truncated' => $truncated,
        ];
    }

    /**
     * Y-m-d real: 2026-02-30 y 2026-13-01 no son fechas validas.
     *
     * @param  mixed  $value
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    private function parseDate($value)
    {
        $date = \DateTime::createFromFormat('!Y-m-d', (string) $value);

        if (! $date || $date->format('Y-m-d') !== (string) $value) {
            throw new InvalidArgumentException('invalid_range');
        }

        return $date->format('Y-m-d');
    }

    /**
     * @param  mixed  $value
     * @param  int  $default
     * @return int
     */
    private function positiveInt($value, $default)
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $value = (int) $value;

        return $value < 1 ? $default : $value;
    }

    /**
     * @param  mixed  $value
     * @return int
     */
    private function perPage($value)
    {
        if ($value === null || $value === '') {
            return 10;
        }

        $value = (int) $value;

        if ($value < 1) {
            return 1;
        }

        return $value > 50 ? 50 : $value;
    }
}
