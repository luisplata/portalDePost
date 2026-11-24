<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Beacon de impresion (REQ-IMP-02..07).
 *
 * Siempre responde 204 cuando la request pasa las validaciones de abuso, aun
 * si la creatividad no existe, no es servible o fue deduplicada (D-04). Solo
 * un payload con esquema invalido es 422. No crea filas por evento: solo el
 * agregado diario.
 */
class ImpressionController extends Controller
{
    protected $adService;

    public function __construct(AdService $adService)
    {
        $this->adService = $adService;
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Nunca $request->validate(): sendBeacon no manda Accept: application/json
        // y Laravel redirigiria con 302 en vez de devolver 422.
        $validator = Validator::make($request->all(), [
            'creative' => 'required|string|max:255',
            'placement' => 'required|string|max:255',
            'uuid' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'invalid_payload'], 422);
        }

        try {
            $servable = $this->adService->isServable(
                (string) $request->input('creative'),
                (string) $request->input('placement')
            );

            if ($servable !== null) {
                $this->adService->recordImpression(
                    $servable->creative_id,
                    $servable->placement_id,
                    $request->input('uuid'),
                    $request->ip()
                );
            }
        } catch (\Throwable $e) {
            // El beacon no debe romper la respuesta: se loguea y se sigue.
            Log::error('ads: no se pudo registrar la impresion', [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->noContent();
    }
}
