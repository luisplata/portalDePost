<?php

namespace Tests\Unit\Publicacion;

use App\Services\Publicacion\ResultadoEntrega;
use Tests\TestCase;

/**
 * Contrato de resultado de entrega (T-S3a-01, REQ-DRV-01, DRV-11).
 */
class ResultadoEntregaTest extends TestCase
{
    public function test_factories_set_result_status_and_redacted_error()
    {
        $entregado = ResultadoEntrega::entregado(200, '42');
        $this->assertSame(ResultadoEntrega::ENTREGADO, $entregado->resultado);
        $this->assertSame(200, $entregado->httpStatus);
        $this->assertSame('42', $entregado->referencia);
        $this->assertNull($entregado->error);
        $this->assertFalse($entregado->invalidaCredencial);

        $temporal = ResultadoEntrega::temporal(429, 30, 'rate_limited:30s');
        $this->assertSame(ResultadoEntrega::TEMPORAL, $temporal->resultado);
        $this->assertSame(429, $temporal->httpStatus);
        $this->assertSame(30, $temporal->espera);
        $this->assertSame('rate_limited:30s', $temporal->error);

        $permanente = ResultadoEntrega::permanente(401, 'credencial rechazada', true);
        $this->assertSame(ResultadoEntrega::PERMANENTE, $permanente->resultado);
        $this->assertTrue($permanente->invalidaCredencial);

        $servidor = ResultadoEntrega::servidor(500, 'boom');
        $this->assertSame(ResultadoEntrega::SERVIDOR, $servidor->resultado);
        $this->assertSame(500, $servidor->httpStatus);

        $ambiguo = ResultadoEntrega::ambiguo(null, 'timeout de lectura');
        $this->assertSame(ResultadoEntrega::AMBIGUO, $ambiguo->resultado);
        $this->assertNull($ambiguo->httpStatus);
    }

    public function test_error_is_truncated_to_500_with_ellipsis()
    {
        $largo = ResultadoEntrega::servidor(500, str_repeat('x', 900));

        $this->assertSame(500, strlen($largo->error));
        $this->assertStringEndsWith('...', $largo->error);
    }
}
