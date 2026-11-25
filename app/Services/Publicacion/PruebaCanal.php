<?php

namespace App\Services\Publicacion;

use App\Producto;
use App\PublicacionCanal;

/**
 * Prueba manual de un canal (REQ-CAN-11).
 *
 * Envia un texto fijo sin imagen, no crea entregas ni toca `published`, y
 * actualiza estado y estado_verificado_at. Los errores no exponen secretos.
 */
class PruebaCanal
{
    /**
     * @return array{ok: bool, mensaje: string, resultado?: ResultadoEntrega}
     */
    public function probar(PublicacionCanal $canal): array
    {
        $driver = $this->driverDe($canal->tipo);

        if ($driver === null) {
            $canal->estado = 'tipo_desconocido';
            $canal->estado_detalle = 'Driver no disponible.';
            $canal->estado_verificado_at = now();
            $canal->save();

            return ['ok' => false, 'mensaje' => 'El tipo de canal no tiene driver.'];
        }

        $credenciales = Secretos::descifrar($canal);

        if ($credenciales === null) {
            $this->marcarCredencialInvalida($canal);

            return ['ok' => false, 'mensaje' => 'Vuelva a cargar las credenciales.'];
        }

        $producto = new Producto();
        $producto->id = 0;
        $producto->nombre = 'Prueba de publicacion';
        $producto->imagen = '#';
        $producto->hotLink = '';

        $resultado = $driver->enviar($producto, $credenciales, url('/'));

        if ($resultado->resultado === ResultadoEntrega::ENTREGADO) {
            $canal->estado = 'ok';
            $canal->estado_detalle = null;
        } elseif ($resultado->invalidaCredencial) {
            $canal->estado = 'invalido_credencial';
            $canal->estado_detalle = 'El proveedor rechazo las credenciales.';
        }

        $canal->estado_verificado_at = now();
        $canal->save();

        return [
            'ok' => $resultado->resultado === ResultadoEntrega::ENTREGADO,
            'mensaje' => $resultado->resultado === ResultadoEntrega::ENTREGADO
                ? 'Prueba enviada; canal verificado.'
                : 'La prueba fallo: '.$resultado->resultado.'.',
            'resultado' => $resultado,
        ];
    }

    /**
     * @return CanalDriver|null
     */
    private function driverDe(string $tipo)
    {
        $clase = config('publicaciones.drivers.'.$tipo);

        if (!is_string($clase) || !class_exists($clase)) {
            return null;
        }

        return app($clase);
    }

    private function marcarCredencialInvalida(PublicacionCanal $canal): void
    {
        $canal->estado = 'invalido_credencial';
        $canal->estado_detalle = 'Vuelva a cargar las credenciales.';
        $canal->estado_verificado_at = now();
        $canal->save();
    }
}
