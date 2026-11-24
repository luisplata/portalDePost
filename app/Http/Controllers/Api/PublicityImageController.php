<?php

namespace App\Http\Controllers\Api;

use App\AdCreative;
use App\ConfigPublicity;
use App\Http\Controllers\Controller;

/**
 * Compatibilidad de la imagen legacy (REQ-CLK-05).
 *
 * - key mapeada en config('ads.legacy_aliases') y creatividad existente ->
 *   302 a su image_url,
 * - si no, se conserva el comportamiento de ConfigPublicity::GetImage,
 * - si no hay ni mapeo ni fila legacy -> 404 JSON.
 *
 * Nunca cuenta click: es solo una imagen.
 */
class PublicityImageController extends Controller
{
    /**
     * GET /api/publicity/image/{key}
     *
     * @param  string  $key
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function show($key)
    {
        $aliases = (array) config('ads.legacy_aliases', []);

        if (isset($aliases[$key])) {
            $creative = AdCreative::query()
                ->where('campaign_key', (string) $aliases[$key])
                ->first();

            if ($creative !== null && (string) $creative->image_url !== '') {
                return redirect()->away($creative->image_url);
            }
        }

        $publicity = ConfigPublicity::query()->where('key', $key)->first();

        if ($publicity === null) {
            return response()->json(['error' => 'Publicity not found'], 404);
        }

        $value = $publicity->GetValue();
        $image = (is_object($value) && isset($value->image)) ? (string) $value->image : '';

        if ($image === '') {
            return response()->json(['error' => 'Publicity not found'], 404);
        }

        return redirect()->away($image);
    }
}
