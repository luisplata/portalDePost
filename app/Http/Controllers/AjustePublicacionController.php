<?php

namespace App\Http\Controllers;

use App\PublicacionAjuste;
use App\Services\Publicacion\AuditoriaPublicacion;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Panel de ajustes de publicacion (REQ-AJUS-01..06, OBS-07, OBS-08).
 */
class AjustePublicacionController extends Controller
{
    public function edit()
    {
        return view('admin.Publicacion.ajustes', [
            'activo' => PublicacionAjuste::activo(),
            'desde' => PublicacionAjuste::desde(),
            'resumen' => PublicacionAjuste::resumenCorrida(),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate(['publicaciones_enabled' => ['nullable', 'boolean']]);

        $activo = (bool) $request->input('publicaciones_enabled', 0);
        $texto = trim((string) $request->input('publicaciones_desde', ''));
        $fecha = $texto === '' ? null : $this->parseFecha($texto);

        if ($activo && $texto === '') {
            throw ValidationException::withMessages([
                'publicaciones_desde' => 'Para encender las publicaciones hay que indicar la fecha de corte.',
            ]);
        }

        if ($texto !== '' && $fecha === null) {
            throw ValidationException::withMessages([
                'publicaciones_desde' => 'La fecha de corte no es valida.',
            ]);
        }

        $antes = PublicacionAjuste::desde();

        PublicacionAjuste::poner(
            PublicacionAjuste::CLAVE_ACTIVO,
            $activo ? '1' : '0'
        );

        if ($fecha !== null) {
            PublicacionAjuste::poner(
                PublicacionAjuste::CLAVE_DESDE,
                $fecha->format('Y-m-d H:i:s')
            );
        }

        AuditoriaPublicacion::registrar(
            'ajustes',
            'edicion',
            ['publicaciones_enabled', 'publicaciones_desde'],
            ['publicaciones_desde' => $antes ? $antes->toDateTimeString() : null],
            ['publicaciones_desde' => $fecha ? $fecha->toDateTimeString() : ($antes ? $antes->toDateTimeString() : null)]
        );

        return redirect('admin/publicaciones/ajustes')
            ->with('mensaje', 'Ajustes guardados.');
    }

    private function parseFecha(string $valor)
    {
        foreach (['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s'] as $formato) {
            try {
                $fecha = Carbon::createFromFormat($formato, $valor, 'UTC');

                if ($fecha !== false) {
                    return $fecha;
                }
            } catch (\Exception $error) {
                // Se prueba el siguiente formato.
            }
        }

        return null;
    }
}
