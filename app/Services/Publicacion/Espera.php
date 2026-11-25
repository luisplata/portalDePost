<?php

namespace App\Services\Publicacion;

/**
 * Pausa entre envios, inyectable para testear sin dormir (REQ-ENT-05).
 */
interface Espera
{
    public function ms(int $milisegundos): void;
}
