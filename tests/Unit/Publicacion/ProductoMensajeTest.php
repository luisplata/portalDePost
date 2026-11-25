<?php

namespace Tests\Unit\Publicacion;

use App\Producto;
use App\Services\Publicacion\ProductoMensaje;
use Tests\TestCase;

/**
 * Mensaje compartido por los drivers (T-S3a-05, REQ-DRV-03, DRV-06, DRV-08,
 * NFR-04).
 */
class ProductoMensajeTest extends TestCase
{
    public function test_link_is_absolute_and_never_the_hotlink()
    {
        $producto = $this->makeProducto(7, 'Producto', 'https://affiliate.example/x');

        $this->assertSame(url('/redirect/7'), ProductoMensaje::link($producto));
        $this->assertStringNotContainsString('affiliate.example', ProductoMensaje::link($producto));
    }

    public function test_long_name_is_truncated_and_link_survives()
    {
        $producto = $this->makeProducto(7, str_repeat('a', 2000));

        $texto = ProductoMensaje::texto($producto);
        $link = ProductoMensaje::link($producto);

        $this->assertStringEndsWith("\n".$link, $texto);
        $this->assertStringContainsString('...', $texto);
        $this->assertLessThanOrEqual(1008, $this->utf16($texto));
    }

    public function test_emoji_outside_the_bmp_are_counted_as_utf16_units_and_kept_valid()
    {
        $producto = $this->makeProducto(8, str_repeat('😀', 2000));

        $texto = ProductoMensaje::texto($producto);

        $this->assertLessThanOrEqual(1024, $this->utf16($texto));
        $this->assertSame(1, preg_match('//u', $texto));
        $this->assertStringEndsWith(ProductoMensaje::link($producto), $texto);
    }

    public function test_texto_uses_the_provided_link_when_given()
    {
        $producto = $this->makeProducto(7, 'Nombre');

        $texto = ProductoMensaje::texto($producto, 'https://app.test/redirect/7?x=1');

        $this->assertStringEndsWith("\nhttps://app.test/redirect/7?x=1", $texto);
    }

    public function test_discord_title_is_truncated_to_256_with_ellipsis()
    {
        $producto = $this->makeProducto(9, str_repeat('b', 1000));

        $titulo = ProductoMensaje::titulo($producto);

        $this->assertLessThanOrEqual(256, $this->utf16($titulo));
        $this->assertStringEndsWith('...', $titulo);
    }

    public function test_imagen_valida_rejects_empty_hash_and_non_http_values()
    {
        foreach ([null, '', '   ', '#', 'no-es-url', 'ftp://example.com/a.jpg'] as $invalida) {
            $this->assertFalse(ProductoMensaje::imagenValida($invalida), var_export($invalida, true));
        }

        $this->assertTrue(ProductoMensaje::imagenValida('https://cdn.example/a.jpg'));
        $this->assertTrue(ProductoMensaje::imagenValida('http://cdn.example/a.jpg'));
    }

    private function makeProducto(int $id, string $nombre, string $hotLink = 'https://affiliate.example/x'): Producto
    {
        $producto = new Producto();
        $producto->id = $id;
        $producto->nombre = $nombre;
        $producto->hotLink = $hotLink;

        return $producto;
    }

    private function utf16(string $texto): int
    {
        return intdiv(strlen(mb_convert_encoding($texto, 'UTF-16LE', 'UTF-8')), 2);
    }
}
