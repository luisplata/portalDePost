<?php

namespace App\Services\Publicacion;

/**
 * Traduce una excepcion de transporte a un resultado clasificado (REQ-DRV-09).
 *
 * Laravel 8.77 no distingue timeout de conexion y de lectura: ambos llegan como
 * ConnectionException con errno 28. La diferencia se lee del handlerContext de
 * Guzzle: `pretransfer_time` mayor a cero significa que la conexion se
 * establecio y el request entro en transferencia (timeout de lectura).
 *
 * Default seguro: ambiguo. Un duplicado es peor que un reintento manual.
 */
class ClasificadorTransporte
{
    /**
     * @param  string[]  $secretos  valores a redactar del mensaje
     */
    public function clasificar(\Throwable $error, array $secretos = []): ResultadoEntrega
    {
        $mensaje = Secretos::redactar($error->getMessage(), $secretos);
        $contexto = $this->contexto($error);
        $errno = isset($contexto['errno']) ? (int) $contexto['errno'] : null;
        $pretransfer = isset($contexto['pretransfer_time'])
            ? (float) $contexto['pretransfer_time']
            : null;

        if (in_array($errno, [6, 7, 35], true)) {
            return ResultadoEntrega::servidor(null, $mensaje);
        }

        if ($errno === 28) {
            // Conexion establecida: el request pudo haberse entregado.
            if ($pretransfer !== null && $pretransfer > 0) {
                return ResultadoEntrega::ambiguo(null, $mensaje);
            }

            return ResultadoEntrega::servidor(null, $mensaje);
        }

        if ($errno === 52) {
            return ResultadoEntrega::ambiguo(null, $mensaje);
        }

        return ResultadoEntrega::ambiguo(null, $mensaje);
    }

    /**
     * Handler context de Guzzle, aunque venga envuelto por Laravel.
     */
    private function contexto(\Throwable $error): array
    {
        while ($error !== null) {
            if (method_exists($error, 'getHandlerContext')) {
                $contexto = $error->getHandlerContext();

                return is_array($contexto) ? $contexto : [];
            }

            $error = $error->getPrevious();
        }

        return [];
    }
}
