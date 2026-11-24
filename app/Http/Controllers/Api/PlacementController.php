<?php

namespace App\Http\Controllers\Api;

use App\AdPlacement;
use App\Http\Controllers\Controller;
use App\Services\AdService;

/**
 * API publica de placements (REQ-API-01, REQ-API-03).
 *
 * Solo lectura, sin auth, y reutiliza la misma seleccion cacheada que el
 * render publico (D-09).
 */
class PlacementController extends Controller
{
    protected $adService;

    public function __construct(AdService $adService)
    {
        $this->adService = $adService;
    }

    /**
     * @param  string  $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($key)
    {
        $placement = AdPlacement::query()->where('key', $key)->first();

        if ($placement === null) {
            return response()->json(['error' => 'placement_not_found'], 404);
        }

        $creatives = $this->adService->presentPublic(
            $this->adService->selectForPlacement($placement->key)
        );

        return response()->json([
            'placement' => [
                'key' => $placement->key,
                'name' => $placement->name,
                'max_items' => $placement->max_items,
            ],
            'creatives' => $creatives,
        ]);
    }
}
