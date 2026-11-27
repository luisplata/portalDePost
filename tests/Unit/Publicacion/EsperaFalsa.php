<?php

namespace Tests\Unit\Publicacion;

use App\Services\Publicacion\Espera;

/**
 * Espia de la pausa entre envios. Ningun test duerme.
 */
final class EsperaFalsa implements Espera
{
    /** @var int[] */
    public $llamadas = [];

    public function ms(int $milisegundos): void
    {
        $this->llamadas[] = $milisegundos;
    }
}
