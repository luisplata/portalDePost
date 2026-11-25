<?php

namespace Tests\Unit\Publicacion;

use App\Services\Publicacion\ClasificadorTransporte;
use App\Services\Publicacion\ResultadoEntrega;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Http\Client\ConnectionException;
use Tests\TestCase;

/**
 * Clasificacion de fallos de transporte sin cURL real (T-S3a-03/04,
 * REQ-DRV-09, DRV-11).
 *
 * Http::fake no ejecuta cURL, por eso la excepcion se construye a mano con
 * errno y pretransfer_time, las dos senales estables de Guzzle 7.4.
 */
class ClasificadorTransporteTest extends TestCase
{
    public function test_connection_refused_is_server_failure()
    {
        $this->assertSame(
            ResultadoEntrega::SERVIDOR,
            $this->clasificar($this->guzzleException(7, 0.0))->resultado
        );
    }

    public function test_dns_and_tls_failures_are_server_failures()
    {
        foreach ([6, 35] as $errno) {
            $this->assertSame(
                ResultadoEntrega::SERVIDOR,
                $this->clasificar($this->guzzleException($errno, 0.0))->resultado,
                'errno '.$errno
            );
        }
    }

    public function test_connect_timeout_is_server_failure_and_read_timeout_is_ambiguous()
    {
        $this->assertSame(
            ResultadoEntrega::SERVIDOR,
            $this->clasificar($this->guzzleException(28, 0.0))->resultado
        );

        $this->assertSame(
            ResultadoEntrega::AMBIGUO,
            $this->clasificar($this->guzzleException(28, 0.42))->resultado
        );
    }

    public function test_empty_reply_is_ambiguous()
    {
        $this->assertSame(
            ResultadoEntrega::AMBIGUO,
            $this->clasificar($this->guzzleException(52, 0.42))->resultado
        );
    }

    public function test_an_unknown_exception_defaults_to_ambiguous()
    {
        $resultado = $this->clasificar(new \RuntimeException('sin contexto'));

        $this->assertSame(ResultadoEntrega::AMBIGUO, $resultado->resultado);
        $this->assertSame('sin contexto', $resultado->error);
    }

    public function test_laravel_connection_exception_unwraps_the_guzzle_cause()
    {
        $guzzle = $this->guzzleException(7, 0.0);

        $error = new ConnectionException($guzzle->getMessage(), 0, $guzzle);

        $this->assertSame(ResultadoEntrega::SERVIDOR, $this->clasificar($error)->resultado);
    }

    public function test_errors_are_redacted_with_the_given_secrets()
    {
        $token = '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw';
        $guzzle = $this->guzzleException(7, 0.0, 'cURL error 7 con '.$token);

        $resultado = (new ClasificadorTransporte())
            ->clasificar($guzzle, [$token]);

        $this->assertStringNotContainsString($token, $resultado->error);
        $this->assertStringNotContainsString('AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw', $resultado->error);
    }

    private function clasificar(\Throwable $error): ResultadoEntrega
    {
        return (new ClasificadorTransporte())->clasificar($error);
    }

    private function guzzleException(
        int $errno,
        float $pretransfer,
        string $mensaje = null
    ): ConnectException {
        return new ConnectException(
            $mensaje ?: ('cURL error '.$errno.': fallo'),
            new Request('POST', 'https://api.telegram.org'),
            null,
            ['errno' => $errno, 'pretransfer_time' => $pretransfer]
        );
    }
}
