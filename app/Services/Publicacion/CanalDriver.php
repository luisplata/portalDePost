<?php

namespace App\Services\Publicacion;

use App\Producto;

/**
 * Contrato de transporte de un tipo de canal (REQ-DRV-01).
 *
 * `validar` no hace red. `enviar` nunca lanza: toda excepcion se clasifica y
 * vuelve como ResultadoEntrega.
 */
interface CanalDriver
{
    public function tipo(): string;

    /**
     * @return array{ok: bool, estado: string, detalle: string|null}
     */
    public function validar(array $credenciales): array;

    public function enviar(Producto $producto, array $credenciales, string $link): ResultadoEntrega;
}
