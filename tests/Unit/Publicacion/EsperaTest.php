<?php

namespace Tests\Unit\Publicacion;

use App\Services\Publicacion\Espera;
use App\Services\Publicacion\EsperaReal;
use Tests\TestCase;

/**
 * Reloj/esperador inyectables (REQ-ENT-05, NFR-08).
 */
class EsperaTest extends TestCase
{
    public function test_fake_wait_spy_records_each_call()
    {
        $falsa = new EsperaFalsa();

        $falsa->ms(800);
        $falsa->ms(800);

        $this->assertSame([800, 800], $falsa->llamadas);
    }

    public function test_container_resolves_the_real_wait_implementation()
    {
        $this->assertInstanceOf(EsperaReal::class, $this->app->make(Espera::class));
    }

    public function test_the_fake_replaces_the_binding_without_sleeping()
    {
        $falsa = new EsperaFalsa();
        $this->app->instance(Espera::class, $falsa);

        $this->app->make(Espera::class)->ms(800);

        $this->assertSame([800], $falsa->llamadas);
    }
}
