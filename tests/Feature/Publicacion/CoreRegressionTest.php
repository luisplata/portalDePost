<?php

namespace Tests\Feature\Publicacion;

use App\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Publicacion\PublicacionTestSupport;

/**
 * Caracterizacion del core antes de tocarlo (T-S5-01, REQ-REG-03..06).
 *
 * Congela el comportamiento actual: alta, edicion, CSV, ficha, redirects,
 * dashboard y claves de /api/*. Los tests de correccion de S5 actualizan solo
 * lo que la spec manda cambiar.
 */
class CoreRegressionTest extends PublicacionTestSupport
{
    use RefreshDatabase;

    public function test_store_forces_published_false()
    {
        $respuesta = $this->asAdmin()->post('/admin/producto', $this->payload());

        $this->assertSame(url('admin/producto?1'), $respuesta->headers->get('Location'));

        $producto = Producto::orderBy('id', 'desc')->first();

        $this->assertSame('Producto nuevo', $producto->nombre);
        $this->assertSame('https://cdn.example/url.jpg', $producto->imagen);
        $this->assertSame(0, (int) $producto->published);
        $this->assertSame(1, (int) $producto->estado);
    }

    public function test_update_persists_estado()
    {
        $producto = $this->makeProducto(['nombre' => 'Original']);

        $respuesta = $this->asAdmin()->put('/admin/producto/'.$producto, array_merge(
            $this->payload(),
            ['nombre' => 'Editado', 'estado' => '0', 'imagen' => 'https://cdn.example/editado.jpg']
        ));

        $this->assertSame(
            url('admin/producto?mensaje=Se modifico el producto con exito&tipo=success'),
            $respuesta->headers->get('Location')
        );

        $fresco = Producto::find($producto);
        $this->assertSame('Editado', $fresco->nombre);
        $this->assertSame(0, (int) $fresco->estado);
    }

    public function test_csv_import_keeps_published_false_and_redirect()
    {
        $csv = "Nombre CSV,https://cdn.example/csv.jpg,PCK CSV,https://affiliate.example/csv,"
            ."2026-11-20 10:00:00,0,,alpha\n";
        $archivo = UploadedFile::fake()->createWithContent('productos.csv', $csv);

        DB::table('categorias')->insert([
            'id' => 1,
            'nombre' => 'Categoria CSV',
            'descripcion' => 'prueba',
            'padre' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $respuesta = $this->asAdmin()->post('/admin/producto/uploadFile', ['logo' => $archivo]);

        $respuesta->assertRedirect(url('admin/producto'));

        $producto = Producto::where('nombre', 'Nombre CSV')->first();

        $this->assertNotNull($producto);
        $this->assertSame(0, (int) $producto->published);
        $this->assertSame('PCK CSV', $producto->NombreLink);
    }

    public function test_content_page_renders_the_product()
    {
        $this->makeProducto(['nombre' => 'FichaVisible']);

        $respuesta = $this->get('/content/FichaVisible');

        $respuesta->assertStatus(200);
        $respuesta->assertSee('FichaVisible');
    }

    public function test_redirects_point_to_the_hotlink()
    {
        $producto = $this->makeProducto([
            'nombre' => 'ProductoRedirect',
            'hotLink' => 'https://affiliate.example/destino',
        ]);

        $this->get('/redirect/'.$producto)
            ->assertRedirect('https://affiliate.example/destino');

        $this->get('/redirection/ProductoRedirect')
            ->assertRedirect('https://affiliate.example/destino');
    }

    public function test_dashboard_header_says_visible_en_la_web()
    {
        $this->makeProducto([
            'nombre' => 'Publicado por fecha',
            'publication_date' => now()->subDay(),
        ]);
        $this->makeProducto([
            'nombre' => 'Aun no visible',
            'publication_date' => now()->addDay(),
        ]);

        $respuesta = $this->asAdmin()->get('/admin/producto');

        $respuesta->assertStatus(200);
        $respuesta->assertSee('Visible en la web?');
        $respuesta->assertDontSee('Es publicado?');
        $respuesta->assertSee('Aun no visible');
    }

    public function test_send_to_web_hook_stub_is_gone()
    {
        $this->assertFalse(
            method_exists(\App\Http\Controllers\ProductoController::class, 'sendToWebHook')
        );
        $this->assertTrue(class_exists(\App\Http\Controllers\ProductoController::class));
        $this->assertFileExists(app_path('Auto.php'));
    }

    public function test_api_keys_match_the_captured_baseline()
    {
        $fixture = json_decode(
            file_get_contents(base_path('tests/Feature/Publicacion/fixtures/api_keys.json')),
            true
        );

        $producto = $this->makeProducto(['tags' => 'alpha-beta']);
        DB::table('streams')->insert([
            'nombre' => 'Stream baseline',
            'imagen' => 'https://cdn.example/stream.jpg',
            'estado' => 1,
            'url' => 'https://example.com/stream',
            'publication_date' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('visit_posts')->insert([
            'producto_id' => $producto,
            'visita' => 3,
            'idoAlPack' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['hot', 'popular', 'banners'] as $endpoint) {
            $body = $this->getJson('/api/'.$endpoint)->assertStatus(200)->json();

            $this->assertSame(
                $fixture['api/'.$endpoint]['item_keys'],
                $this->keysOf(isset($body[0]) ? $body[0] : []),
                $endpoint
            );
        }

        $packs = $this->getJson('/api/packs')->assertStatus(200)->json();
        $this->assertSame($fixture['api/packs']['keys'], $this->keysOf($packs));
        $this->assertSame(
            $fixture['api/packs']['data_item_keys'],
            $this->keysOf($packs['data'][0])
        );

        $home = $this->getJson('/api/home')->assertStatus(200)->json();
        $this->assertSame($fixture['api/home']['keys'], $this->keysOf($home));

        foreach (['banners', 'hot', 'popular'] as $nido) {
            $this->assertSame(
                $fixture['api/home']['nested'][$nido]['item_keys'],
                $this->keysOf($home[$nido][0]),
                $nido
            );
        }

        $this->assertSame(
            $fixture['api/home']['nested']['packs']['keys'],
            $this->keysOf($home['packs'])
        );

        $tags = $this->getJson('/api/tags')->assertStatus(200)->json();
        $this->assertSame('string', gettype($tags[0]));

        $modelo = $this->getJson('/api/model/'.$producto)->assertStatus(200)->json();
        $this->assertSame($fixture['api/model/{id}']['keys'], $this->keysOf($modelo));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Producto nuevo',
            'url' => 'https://cdn.example/url.jpg',
            'imagen' => 'https://cdn.example/url.jpg',
            'categoria_id' => $this->makeCategoria(),
            'nombreLink' => 'PCK',
            'hotLink' => 'https://affiliate.example/hot',
            'publication_date' => now()->subHour()->toDateTimeString(),
            'isVideo' => '0',
            'url_video' => '',
            'tags' => 'alpha',
        ], $overrides);
    }

    private function keysOf($item): array
    {
        if (!is_array($item)) {
            return [];
        }

        $keys = array_keys($item);
        sort($keys);

        return $keys;
    }

    private function makeCategoria(): int
    {
        return DB::table('categorias')->insertGetId([
            'nombre' => 'Categoria '.uniqid(),
            'descripcion' => 'prueba',
            'padre' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
