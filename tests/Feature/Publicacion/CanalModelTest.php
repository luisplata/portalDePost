<?php

namespace Tests\Feature\Publicacion;

use App\PublicacionCanal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Modelo base de canales (T-S1-04, REQ-CAN-06, ENT-03).
 */
class CanalModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_canal_model_uses_the_publication_canales_table_and_casts_config()
    {
        $canal = PublicacionCanal::create([
            'tipo' => 'telegram',
            'nombre' => 'Telegram principal',
            'habilitado' => 1,
            'vigente_desde' => now(),
            'credenciales' => 'raw-placeholder',
            'config' => ['silencioso' => true, 'lote' => 3],
            'estado' => 'ok',
        ]);

        $fresh = PublicacionCanal::find($canal->id);

        $this->assertSame('publicacion_canales', $fresh->getTable());
        $this->assertEquals(['silencioso' => true, 'lote' => 3], $fresh->config);
        $this->assertSame('telegram', $fresh->tipo);
    }

    public function test_canal_model_soft_deletes_and_still_resolves_its_envios()
    {
        $canal = PublicacionCanal::create([
            'tipo' => 'discord',
            'nombre' => 'Discord viejo',
            'habilitado' => 1,
            'vigente_desde' => now(),
            'credenciales' => 'raw-placeholder',
            'estado' => 'ok',
        ]);

        $producto = DB::table('productos')->insertGetId([
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

        DB::table('publicacion_envios')->insert([
            'producto_id' => $producto,
            'publicacion_canal_id' => $canal->id,
            'canal_tipo' => 'discord',
            'estado' => 'enviado',
            'intentos' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $canal->delete();

        $this->assertSame(1, DB::table('publicacion_canales')->count());
        $this->assertNotNull(DB::table('publicacion_canales')->value('deleted_at'));
        $this->assertNull(PublicacionCanal::find($canal->id));
        $this->assertNotNull(PublicacionCanal::withTrashed()->find($canal->id));
    }
}
