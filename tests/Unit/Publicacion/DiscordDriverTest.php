<?php

namespace Tests\Unit\Publicacion;

use App\Producto;
use App\Services\Publicacion\DiscordDriver;
use App\Services\Publicacion\ProductoMensaje;
use App\Services\Publicacion\ResultadoEntrega;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Driver de Discord (T-S3b-03/04, REQ-DRV-05, DRV-06, DRV-07, DRV-08,
 * DRV-10, DRV-11).
 */
class DiscordDriverTest extends TestCase
{
    private const WEBHOOK = 'https://discord.com/api/webhooks/1234/abcdEFGH_-123';

    public function test_discord_posts_embed_with_title_url_and_image()
    {
        Http::fake(['discord.com/*' => Http::response('', 204)]);

        $producto = $this->makeProducto();
        $resultado = (new DiscordDriver())->enviar(
            $producto,
            $this->credenciales(),
            ProductoMensaje::link($producto)
        );

        $this->assertSame(ResultadoEntrega::ENTREGADO, $resultado->resultado);
        $this->assertSame(204, $resultado->httpStatus);
        $this->assertNull($resultado->referencia);
        Http::assertSentCount(1);

        Http::assertSent(function ($request) use ($producto) {
            $data = $request->data();
            $embed = $data['embeds'][0];

            return $request->url() === self::WEBHOOK
                && $embed['title'] === ProductoMensaje::titulo($producto)
                && $embed['url'] === ProductoMensaje::link($producto)
                && $embed['image']['url'] === $producto->imagen;
        });
    }

    public function test_discord_200_keeps_the_message_id_as_reference()
    {
        Http::fake(['discord.com/*' => Http::response(['id' => '999'], 200)]);

        $producto = $this->makeProducto();
        $resultado = (new DiscordDriver())->enviar(
            $producto,
            $this->credenciales(),
            ProductoMensaje::link($producto)
        );

        $this->assertSame(ResultadoEntrega::ENTREGADO, $resultado->resultado);
        $this->assertSame('999', $resultado->referencia);
    }

    public function test_discord_invalid_image_omits_embed_image()
    {
        Http::fake(['discord.com/*' => Http::response('', 204)]);

        $producto = $this->makeProducto(['imagen' => '#']);
        (new DiscordDriver())->enviar(
            $producto,
            $this->credenciales(),
            ProductoMensaje::link($producto)
        );

        Http::assertSent(function ($request) {
            $embed = $request->data()['embeds'][0];

            return !array_key_exists('image', $embed)
                && $embed['url'] === ProductoMensaje::link($this->makeProducto());
        });
    }

    public function test_discord_429_reads_body_and_header_retry_after()
    {
        $casos = [
            [Http::response(['retry_after' => 1.5], 429), 2],
            [Http::response('', 429, ['Retry-After' => '3']), 3],
            [Http::response('', 429), 60],
        ];

        $indice = 0;
        Http::fake(function () use (&$indice, $casos) {
            return $casos[$indice++][0];
        });

        foreach ($casos as $caso) {
            $producto = $this->makeProducto();
            $resultado = (new DiscordDriver())->enviar(
                $producto,
                $this->credenciales(),
                ProductoMensaje::link($producto)
            );

            $this->assertSame(ResultadoEntrega::TEMPORAL, $resultado->resultado);
            $this->assertSame(429, $resultado->httpStatus);
            $this->assertSame($caso[1], $resultado->espera);
        }
    }

    public function test_discord_auth_errors_invalidate_credentials()
    {
        $statuses = [401, 403, 404];
        $indice = 0;
        Http::fake(function () use (&$indice, $statuses) {
            return Http::response('', $statuses[$indice++]);
        });

        foreach ($statuses as $status) {
            $producto = $this->makeProducto();
            $resultado = (new DiscordDriver())->enviar(
                $producto,
                $this->credenciales(),
                ProductoMensaje::link($producto)
            );

            $this->assertSame(ResultadoEntrega::PERMANENTE, $resultado->resultado);
            $this->assertTrue($resultado->invalidaCredencial);
            $this->assertSame($status, $resultado->httpStatus);
        }
    }

    public function test_discord_5xx_is_server_failure()
    {
        Http::fake(['discord.com/*' => Http::response('', 503)]);

        $producto = $this->makeProducto();
        $resultado = (new DiscordDriver())->enviar(
            $producto,
            $this->credenciales(),
            ProductoMensaje::link($producto)
        );

        $this->assertSame(ResultadoEntrega::SERVIDOR, $resultado->resultado);
        $this->assertSame(503, $resultado->httpStatus);
    }

    private function credenciales(): array
    {
        return ['webhook_url' => self::WEBHOOK];
    }

    private function makeProducto(array $overrides = []): Producto
    {
        $producto = new Producto();
        $producto->id = 12;
        $producto->nombre = isset($overrides['nombre']) ? $overrides['nombre'] : 'Producto';
        $producto->imagen = isset($overrides['imagen'])
            ? $overrides['imagen']
            : 'https://cdn.example/product.jpg';
        $producto->hotLink = 'https://affiliate.example/hot';

        return $producto;
    }

    public function test_a_very_long_image_url_is_omitted_to_fit_the_embed_limit()
    {
        Http::fake(['discord.com/*' => Http::response('', 204)]);

        $producto = $this->makeProducto([
            'imagen' => 'https://cdn.example/'.str_repeat('a', 7000),
        ]);

        $resultado = (new DiscordDriver())->enviar(
            $producto,
            $this->credenciales(),
            ProductoMensaje::link($producto)
        );

        $this->assertSame(ResultadoEntrega::ENTREGADO, $resultado->resultado);
        Http::assertSent(function ($request) {
            $embed = $request->data()['embeds'][0];

            return !array_key_exists('image', $embed)
                && strlen(json_encode($embed)) <= 6000;
        });
    }
}
