<?php

namespace App\Http\Controllers;

use App\Services\AdMetricsService;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Series diarias de publicidad para las graficas del admin (REQ-MET-01..04).
 *
 * Es data del admin (middleware `logeado`), no API publica. El rango entra y
 * sale en FECHA LOCAL de config('ads.timezone'), sin conversion (E-1).
 */
class AdMetricsController extends Controller
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        $service = app(AdMetricsService::class);

        try {
            list($from, $to) = $service->range($request->query('from'), $request->query('to'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'invalid_range'], 422);
        }

        // Purga oportunista: nunca puede romper la respuesta (REQ-PRV-03).
        $service->purgeIfDue();

        $series = $service->series($from, $to);

        return response()->json([
            'from' => $from,
            'to' => $to,
            'series' => $series,
            'totals' => $service->totals($series),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
