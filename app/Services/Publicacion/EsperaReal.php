<?php

namespace App\Services\Publicacion;

/**
 * Pausa real de la corrida. Solo se usa fuera de los tests.
 */
class EsperaReal implements Espera
{
    public function ms(int $milisegundos): void
    {
        usleep($milisegundos * 1000);
    }
}
