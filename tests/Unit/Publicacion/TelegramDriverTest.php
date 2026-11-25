<?php

namespace Tests\Unit\Publicacion;

use App\Producto;
use App\Services\Publicacion\ProductoMensaje;
use App\Services\Publicacion\ResultadoEntrega;
use App\Services\Publicacion\TelegramDriver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Driver de Telegram (T-S3b-01/02, REQ-DRV-02, DRV-03, DRV-04, DRV-08,
 * DRV-10, DRV-11).
 */
class TelegramDriverTest extends TestCase
{
    private const TOKEN = '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw';
    private const CHAT = '-1001234567890';

    public function test_telegram_sends_photo_with_chat_id_photo_and_literal_caption()
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 42],
            ], 200),
        ]);

        $producto = $this->makeProducto();
        $resultado = (new TelegramDriver())->enviar(
            $producto,
            $this->credenciales(),
            ProductoMensaje::link($producto)
        );

        $this->assertSame(ResultadoEntrega::ENTREGADO, $resultado->resultado);
        $this->assertSame('42', $resultado->referencia);
        Http::assertSentCount(1);

        Http::assertSent(function ($request) use ($producto) {
            $data = $request->data();

            return $request->url() === 'https://api.telegram.org/bot'.self::TOKEN.'/sendPhoto'
                && $request->method() === 'POST'
                && $data['chat_id'] === self::CHAT
                && $data['photo'] === $producto->imagen
                && $data['caption'] === ProductoMensaje::texto($producto)
                && !isset($data['parse_mode'])
                && in_array((string) $data['disable_notification'], ['0', '', 'false'], true);
        });
    }

    public function test_telegram_400_falls_back_to_send_message_once()
    {
        $this->fakeTelegram([
            'api.telegram.org/*/sendPhoto' => Http::response(['ok' => false, 'description' => 'wrong file'], 400),
            'api.telegram.org/*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 7],
            ], 200),
        ]);

        $producto = $this->makeProducto();
        $resultado = (new TelegramDriver())->enviar(
            $producto,
            $this->credenciales(),
            ProductoMensaje::link($producto)
        );

        $this->assertSame(ResultadoEntrega::ENTREGADO, $resultado->resultado);
        $this->assertSame('7', $resultado->referencia);
        Http::assertSentCount(2);

        $enviadas = Http::recorded();
        $this->assertStringEndsWith('/sendMessage', $enviadas[1][0]->url());
        $this->assertSame(
            ProductoMensaje::texto($producto),
            $enviadas[1][0]->data()['text']
        );
    }

    public function test_telegram_invalid_image_goes_straight_to_send_message()
    {
        $this->fakeTelegram([
            'api.telegram.org/*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 8],
            ], 200),
        ]);

        $producto = $this->makeProducto(['imagen' => '#']);
        $resultado = (new TelegramDriver())->enviar(
            $producto,
            $this->credenciales(),
            ProductoMensaje::link($producto)
        );

        $this->assertSame(ResultadoEntrega::ENTREGADO, $resultado->resultado);
        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return strpos($request->url(), '/sendMessage') !== false
                && isset($request->data()['text']);
        });
    }

    public function test_telegram_auth_errors_are_permanent_and_invalidate_credentials()
    {
        $statuses = [401, 403, 404];
        $indice = 0;

        Http::fake(function () use (&$indice, $statuses) {
            return Http::response(['ok' => false, 'description' => 'nope'], $statuses[$indice++]);
        });

        foreach ($statuses as $status) {
            $producto = $this->makeProducto();
            $resultado = (new TelegramDriver())->enviar(
                $producto,
                $this->credenciales(),
                ProductoMensaje::link($producto)
            );

            $this->assertSame(ResultadoEntrega::PERMANENTE, $resultado->resultado, 'status '.$status);
            $this->assertTrue($resultado->invalidaCredencial);
            $this->assertSame($status, $resultado->httpStatus);
        }
    }

    public function test_telegram_429_reads_parameters_retry_after()
    {
        $this->fakeTelegram([
            'api.telegram.org/*' => Http::response([
                'ok' => false,
                'parameters' => ['retry_after' => 30],
            ], 429),
        ]);

        $producto = $this->makeProducto();
        $resultado = (new TelegramDriver())->enviar(
            $producto,
            $this->credenciales(),
            ProductoMensaje::link($producto)
        );

        $this->assertSame(ResultadoEntrega::TEMPORAL, $resultado->resultado);
        $this->assertSame(429, $resultado->httpStatus);
        $this->assertSame(30, $resultado->espera);
        Http::assertSentCount(1);
    }

    public function test_telegram_5xx_is_server_failure()
    {
        $this->fakeTelegram([
            'api.telegram.org/*' => Http::response(['ok' => false], 500),
        ]);

        $producto = $this->makeProducto();
        $resultado = (new TelegramDriver())->enviar(
            $producto,
            $this->credenciales(),
            ProductoMensaje::link($producto)
        );

        $this->assertSame(ResultadoEntrega::SERVIDOR, $resultado->resultado);
        $this->assertSame(500, $resultado->httpStatus);
    }

    /**
     * @param  array<string, \Illuminate\Http\Client\Response>  $stubs
     */
    private function fakeTelegram(array $stubs)
    {
        Http::fake($stubs);
    }

    private function credenciales(): array
    {
        return ['token' => self::TOKEN, 'chat_id' => self::CHAT];
    }

    private function makeProducto(array $overrides = []): Producto
    {
        $producto = new Producto();
        $producto->id = 11;
        $producto->nombre = isset($overrides['nombre']) ? $overrides['nombre'] : 'Producto';
        $producto->imagen = isset($overrides['imagen'])
            ? $overrides['imagen']
            : 'https://cdn.example/product.jpg';
        $producto->hotLink = 'https://affiliate.example/hot';

        return $producto;
    }
}
