<?php

namespace App\Services\Publicacion;

use Illuminate\Support\Facades\Log;

/**
 * Auditoria del admin de publicaciones (REQ-OBS-08).
 *
 * Registra nombres de campos y, solo cuando es seguro, valores como fechas.
 * Nunca credenciales ni cuerpos.
 */
class AuditoriaPublicacion
{
    public static function registrar(
        string $recurso,
        string $accion,
        array $campos,
        array $antes = [],
        array $despues = []
    ): void {
        $admin = session('admin');

        Log::info('publicacion.auditoria', [
            'admin_id' => is_object($admin) ? ($admin->id ?? null) : null,
            'admin_email' => is_object($admin) ? ($admin->email ?? null) : null,
            'recurso' => $recurso,
            'accion' => $accion,
            'campos' => $campos,
            'antes' => $antes,
            'despues' => $despues,
        ]);
    }
}
