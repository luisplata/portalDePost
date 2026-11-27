<?php

namespace Tests\Feature\Publicacion;

use App\PublicacionCanal;
use App\PublicacionEnvio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Modelo de entregas (T-S1-06, REQ-ENT-06, ENT-11, OBS-02).
 */
class EnvioModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_envio_model_exposes_states_and_relations()
    {
        $this->assertSame('pendiente', PublicacionEnvio::PENDIENTE);
        $this->assertSame('enviado', PublicacionEnvio::ENVIADO);
        $this->assertSame('fallido', PublicacionEnvio::FALLIDO);
        $this->assertSame('incierto', PublicacionEnvio::INCIERTO);

        $producto = $this->insertProducto();
        $canal = PublicacionCanal::create([
            'tipo' => 'telegram',
            'nombre' => 'Telegram principal',
            'habilitado' => 1,
            'vigente_desde' => now(),
            'credenciales' => 'raw-placeholder',
            'estado' => 'ok',
        ]);

        $envio = PublicacionEnvio::create([
            'producto_id' => $producto,
            'publicacion_canal_id' => $canal->id,
            'canal_tipo' => 'telegram',
            'estado' => PublicacionEnvio::PENDIENTE,
            'intentos' => 2,
            'proximo_intento_at' => '2026-11-25 10:00:00',
        ]);

        $fresh = PublicacionEnvio::find($envio->id);

        $this->assertSame('publicacion_envios', $fresh->getTable());
        $this->assertSame(2, $fresh->intentos);
        $this->assertSame('2026-11-25 10:00:00', $fresh->proximo_intento_at->toDateTimeString());
        $this->assertSame($producto, $fresh->producto->id);
        $this->assertSame('Telegram principal', $fresh->canal->nombre);
        $this->assertSame(1, $canal->envios()->count());
    }

    public function test_canal_relation_resolves_even_when_the_channel_was_soft_deleted()
    {
        $producto = $this->insertProducto();
        $canal = PublicacionCanal::create([
            'tipo' => 'discord',
            'nombre' => 'Discord viejo',
            'habilitado' => 0,
            'vigente_desde' => now(),
            'credenciales' => 'raw-placeholder',
            'estado' => 'ok',
        ]);

        $envio = PublicacionEnvio::create([
            'producto_id' => $producto,
            'publicacion_canal_id' => $canal->id,
            'canal_tipo' => 'discord',
            'estado' => PublicacionEnvio::ENVIADO,
            'intentos' => 0,
        ]);

        $canal->delete();

        $fresh = PublicacionEnvio::find($envio->id);

        $this->assertNotNull($fresh->canal);
        $this->assertSame('Discord viejo', $fresh->canal->nombre);
        $this->assertNotNull($fresh->canal->deleted_at);
    }

    public function test_producto_exposes_envios_relation()
    {
        $productoId = $this->insertProducto();
        $canal = PublicacionCanal::create([
            'tipo' => 'telegram',
            'nombre' => 'Telegram relacion',
            'habilitado' => 1,
            'vigente_desde' => now(),
            'credenciales' => ['token' => '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw', 'chat_id' => '-1001234567890'],
            'estado' => 'ok',
        ]);
        $this->makeEnvio($productoId, $canal->id);

        $producto = \App\Producto::find($productoId);

        $this->assertSame(1, $producto->envios()->count());
    }

    private function makeEnvio(int $productoId, int $canalId): void
    {
        PublicacionEnvio::create([
            'producto_id' => $productoId,
            'publicacion_canal_id' => $canalId,
            'canal_tipo' => 'telegram',
            'estado' => PublicacionEnvio::PENDIENTE,
            'intentos' => 0,
        ]);
    }

    private function insertProducto(): int
    {
        return DB::table('productos')->insertGetId([
            'nombre' => 'Producto '.uniqid(),
            'imagen' => 'https://cdn.example/product.jpg',
            'categorias_id' => DB::table('categorias')->insertGetId([
                'nombre' => 'Categoria '.uniqid(),
                'descripcion' => 'prueba',
                'padre' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'estado' => 1,
            'NombreLink' => 'PCK 1',
            'hotLink' => 'https://example.com/hot',
            'publication_date' => now()->subDay(),
            'isVideo' => '0',
            'url_video' => '',
            'tags' => 'alpha',
            'published' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
