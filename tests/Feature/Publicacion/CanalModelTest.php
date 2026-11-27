<?php

namespace Tests\Feature\Publicacion;

use App\PublicacionCanal;
use App\Services\Publicacion\Secretos;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Modelo base de canales (T-S1-04, REQ-CAN-06, ENT-03).
 */
class CanalModelTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

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

    public function test_credentials_cast_is_encrypted_and_scope_excludes_broken_channels()
    {
        $credenciales = [
            'token' => '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw',
            'chat_id' => '-1001234567890',
        ];

        $operativo = PublicacionCanal::create([
            'tipo' => 'telegram',
            'nombre' => 'Operativo',
            'habilitado' => 1,
            'vigente_desde' => now(),
            'credenciales' => $credenciales,
            'estado' => 'ok',
        ]);

        $apagado = PublicacionCanal::create([
            'tipo' => 'telegram',
            'nombre' => 'Apagado',
            'habilitado' => 0,
            'vigente_desde' => now(),
            'credenciales' => $credenciales,
            'estado' => 'ok',
        ]);

        $roto = PublicacionCanal::create([
            'tipo' => 'discord',
            'nombre' => 'Roto',
            'habilitado' => 1,
            'vigente_desde' => now(),
            'credenciales' => ['webhook_url' => 'https://discord.com/api/webhooks/1/abc'],
            'estado' => 'invalido_credencial',
        ]);

        $eliminado = PublicacionCanal::create([
            'tipo' => 'telegram',
            'nombre' => 'Eliminado',
            'habilitado' => 1,
            'vigente_desde' => now(),
            'credenciales' => $credenciales,
            'estado' => 'ok',
        ]);
        $eliminado->delete();

        $crudo = DB::table('publicacion_canales')
            ->where('id', $operativo->id)
            ->value('credenciales');

        $this->assertIsString($crudo);
        $this->assertStringNotContainsString($credenciales['token'], $crudo);
        $this->assertSame($credenciales, PublicacionCanal::find($operativo->id)->credenciales);

        $segundoCrudo = DB::table('publicacion_canales')
            ->where('id', $apagado->id)
            ->value('credenciales');
        $this->assertNotSame($crudo, $segundoCrudo);

        $this->assertSame(
            [$operativo->id],
            PublicacionCanal::operativos()->pluck('id')->all()
        );
    }

    public function test_enabling_a_channel_resets_vigencia_to_now()
    {
        Carbon::setTestNow('2026-11-25 10:00:00');

        $canal = PublicacionCanal::create([
            'tipo' => 'telegram',
            'nombre' => 'Apagado tres dias',
            'habilitado' => 0,
            'vigente_desde' => '2026-11-22 09:00:00',
            'credenciales' => [
                'token' => '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw',
                'chat_id' => '-1001234567890',
            ],
            'estado' => 'ok',
        ]);

        $canal->aplicarHabilitado(true);
        $canal->refresh();

        $this->assertTrue($canal->habilitado);
        $this->assertSame('2026-11-25 10:00:00', $canal->vigente_desde->toDateTimeString());

        Carbon::setTestNow('2026-11-26 12:00:00');

        $canal->aplicarHabilitado(true);
        $canal->refresh();
        $this->assertSame('2026-11-25 10:00:00', $canal->vigente_desde->toDateTimeString());

        $canal->aplicarHabilitado(false);
        $canal->refresh();
        $this->assertFalse($canal->habilitado);
        $this->assertSame('2026-11-25 10:00:00', $canal->vigente_desde->toDateTimeString());

        $canal->aplicarHabilitado(false);
        $canal->refresh();
        $this->assertFalse($canal->habilitado);
        $this->assertSame('2026-11-25 10:00:00', $canal->vigente_desde->toDateTimeString());
    }

    public function test_rotated_app_key_degrades_only_that_channel()
    {
        $credenciales = [
            'token' => '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw',
            'chat_id' => '-1001234567890',
        ];

        $legible = PublicacionCanal::create([
            'tipo' => 'telegram',
            'nombre' => 'Legible',
            'habilitado' => 1,
            'vigente_desde' => now(),
            'credenciales' => $credenciales,
            'estado' => 'ok',
        ]);

        $ilegible = PublicacionCanal::create([
            'tipo' => 'telegram',
            'nombre' => 'Ilegible',
            'habilitado' => 1,
            'vigente_desde' => now(),
            'credenciales' => $credenciales,
            'estado' => 'ok',
        ]);

        $otraClave = new Encrypter(random_bytes(32), 'AES-256-CBC');
        DB::table('publicacion_canales')->where('id', $ilegible->id)->update([
            'credenciales' => $otraClave->encrypt(json_encode($credenciales)),
        ]);

        $this->assertSame(
            $credenciales,
            Secretos::descifrar(PublicacionCanal::find($legible->id))
        );
        $this->assertNull(Secretos::descifrar(PublicacionCanal::find($ilegible->id)));
        $this->assertCount(2, PublicacionCanal::operativos()->get());
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
