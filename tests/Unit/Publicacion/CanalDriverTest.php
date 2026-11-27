<?php

namespace Tests\Unit\Publicacion;

use App\Producto;
use App\Services\Publicacion\CanalDriver;
use App\Services\Publicacion\ResultadoEntrega;
use Tests\TestCase;

/**
 * Contrato de driver de canal (T-S3a-02, REQ-DRV-01).
 */
class CanalDriverTest extends TestCase
{
    public function test_a_driver_declares_type_and_never_leaks_exceptions()
    {
        $driver = new class implements CanalDriver {
            public function tipo(): string
            {
                return 'fake';
            }

            public function validar(array $credenciales): array
            {
                return ['ok' => true, 'estado' => 'ok', 'detalle' => null];
            }

            public function enviar(Producto $producto, array $credenciales, string $link): ResultadoEntrega
            {
                return ResultadoEntrega::entregado(200, '1');
            }
        };

        $this->assertInstanceOf(CanalDriver::class, $driver);
        $this->assertSame('fake', $driver->tipo());
        $this->assertSame(
            ['ok' => true, 'estado' => 'ok', 'detalle' => null],
            $driver->validar([])
        );

        $resultado = $driver->enviar(new Producto(), [], 'https://example.test/redirect/1');

        $this->assertSame(ResultadoEntrega::ENTREGADO, $resultado->resultado);
        $this->assertSame('1', $resultado->referencia);
    }
}
