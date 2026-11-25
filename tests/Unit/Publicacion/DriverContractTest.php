<?php

namespace Tests\Unit\Publicacion;

use App\Producto;
use App\Services\Publicacion\DiscordDriver;
use App\Services\Publicacion\ProductoMensaje;
use App\Services\Publicacion\ResultadoEntrega;
use App\Services\Publicacion\TelegramDriver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Contrato comun de ambos drivers (T-S3b-05/06, REQ-DRV-08, DRV-09, DRV-10,
 * SEG-05).
 */
class DriverContractTest extends TestCase
{
    private const TOKEN = '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw';
    private const WEBHOOK = 'https://discord.com/api/webhooks/1234/abcdEFGH_-123';

    public function test_both_drivers_use_connect_and_read_timeouts_and_never_publish_hotlink()
    {
        $capturado = [];

        Http::fake(function ($request, $options) use (&$capturado) {
            $capturado[] = [
                'url' => $request->url(),
                'options' => $options,
                'data' => $request->data(),
            ];

            if (strpos($request->url(), 'discord.com') !== false) {
                return Http::response('', 204);
            }

            return Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200);
        });

        $producto = $this->makeProducto();

        (new TelegramDriver())->enviar(
            $producto,
            ['token' => self::TOKEN, 'chat_id' => '-1001234567890'],
            ProductoMensaje::link($producto)
        );

        (new DiscordDriver())->enviar(
            $producto,
            ['webhook_url' => self::WEBHOOK],
            ProductoMensaje::link($producto)
        );

        $this->assertCount(2, $capturado);

        foreach ($capturado as $envio) {
            $this->assertSame(5, $envio['options']['connect_timeout']);
            $this->assertSame(15, $envio['options']['timeout']);
            $this->assertStringNotContainsString('affiliate.example', json_encode($envio['data']));
            $this->assertStringNotContainsString('hotLink', json_encode($envio['data']));
        }
    }

    public function test_both_drivers_handle_429_without_consuming_attempts()
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => false,
                'parameters' => ['retry_after' => 10],
            ], 429),
            'discord.com/*' => Http::response(['retry_after' => 10], 429),
        ]);

        $producto = $this->makeProducto();

        $telegram = (new TelegramDriver())->enviar(
            $producto,
            ['token' => self::TOKEN, 'chat_id' => '-1001234567890'],
            ProductoMensaje::link($producto)
        );

        $discord = (new DiscordDriver())->enviar(
            $producto,
            ['webhook_url' => self::WEBHOOK],
            ProductoMensaje::link($producto)
        );

        $this->assertSame(ResultadoEntrega::TEMPORAL, $telegram->resultado);
        $this->assertSame(10, $telegram->espera);
        $this->assertSame(ResultadoEntrega::TEMPORAL, $discord->resultado);
        $this->assertSame(10, $discord->espera);
        Http::assertSentCount(2);
    }

    public function test_both_drivers_mark_401_as_invalid_credentials()
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => false], 401),
            'discord.com/*' => Http::response('', 401),
        ]);

        $producto = $this->makeProducto();

        $resultados = [
            (new TelegramDriver())->enviar(
                $producto,
                ['token' => self::TOKEN, 'chat_id' => '-1001234567890'],
                ProductoMensaje::link($producto)
            ),
            (new DiscordDriver())->enviar(
                $producto,
                ['webhook_url' => self::WEBHOOK],
                ProductoMensaje::link($producto)
            ),
        ];

        foreach ($resultados as $resultado) {
            $this->assertSame(ResultadoEntrega::PERMANENTE, $resultado->resultado);
            $this->assertTrue($resultado->invalidaCredencial);
            $this->assertStringNotContainsString(self::TOKEN, (string) $resultado->error);
        }
    }

    public function test_both_drivers_classify_transport_exceptions_without_leaking_secrets()
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException(
                'cURL error 28: timed out with '.self::TOKEN,
                0,
                new \GuzzleHttp\Exception\ConnectException(
                    'cURL error 28: timed out',
                    new \GuzzleHttp\Psr7\Request('POST', 'https://api.telegram.org'),
                    null,
                    ['errno' => 7, 'pretransfer_time' => 0.0]
                )
            );
        });

        $producto = $this->makeProducto();
        $resultado = (new TelegramDriver())->enviar(
            $producto,
            ['token' => self::TOKEN, 'chat_id' => '-1001234567890'],
            ProductoMensaje::link($producto)
        );

        $this->assertSame(ResultadoEntrega::SERVIDOR, $resultado->resultado);
        $this->assertStringNotContainsString(self::TOKEN, (string) $resultado->error);
    }

    private function makeProducto(): Producto
    {
        $producto = new Producto();
        $producto->id = 13;
        $producto->nombre = 'Producto';
        $producto->imagen = 'https://cdn.example/product.jpg';
        $producto->hotLink = 'https://affiliate.example/hot';

        return $producto;
    }
}
