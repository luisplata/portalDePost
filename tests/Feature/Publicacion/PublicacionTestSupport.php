<?php

namespace Tests\Feature\Publicacion;

use App\PublicacionAjuste;
use App\PublicacionCanal;
use App\PublicacionEnvio;
use App\Services\Publicacion\Espera;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Unit\Publicacion\EsperaFalsa;

/**
 * Helpers compartidos por S4/S5. El nombre no termina en Test para que
 * PHPUnit no lo ejecute como suite.
 */
abstract class PublicacionTestSupport extends TestCase
{
    /**
     * Sesion de admin que acepta el middleware `logeado`.
     *
     * @return $this
     */
    protected function asAdmin()
    {
        return $this->withSession(['admin' => (object) ['nombre' => 'tester']]);
    }

    protected function makeAjustes(array $overrides = []): void
    {
        $valores = array_merge([
            PublicacionAjuste::CLAVE_ACTIVO => '1',
            PublicacionAjuste::CLAVE_DESDE => now()->subDay()->toDateTimeString(),
        ], $overrides);

        foreach ($valores as $clave => $valor) {
            PublicacionAjuste::poner($clave, $valor);
        }
    }

    protected function makeCanal(string $tipo = 'telegram', array $overrides = []): PublicacionCanal
    {
        $credenciales = $tipo === 'telegram'
            ? [
                'token' => '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw',
                'chat_id' => '-1001234567890',
            ]
            : ['webhook_url' => 'https://discord.com/api/webhooks/1234/abcdEFGH_-123'];

        return PublicacionCanal::create(array_merge([
            'tipo' => $tipo,
            'nombre' => ucfirst($tipo).' '.uniqid(),
            'habilitado' => 1,
            'vigente_desde' => now()->subDay(),
            'credenciales' => $credenciales,
            'estado' => 'ok',
        ], $overrides));
    }

    protected function makeProducto(array $overrides = []): int
    {
        return DB::table('productos')->insertGetId(array_merge([
            'nombre' => 'Producto '.uniqid(),
            'imagen' => 'https://cdn.example/product.jpg',
            'categorias_id' => $this->makeCategoria(),
            'estado' => 1,
            'NombreLink' => 'PCK 1',
            'hotLink' => 'https://affiliate.example/hot',
            'publication_date' => now()->subHour(),
            'isVideo' => '0',
            'url_video' => '',
            'tags' => 'alpha',
            'published' => 0,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ], $overrides));
    }

    protected function makeEnvio(int $productoId, int $canalId, array $overrides = []): PublicacionEnvio
    {
        return PublicacionEnvio::create(array_merge([
            'producto_id' => $productoId,
            'publicacion_canal_id' => $canalId,
            'canal_tipo' => 'telegram',
            'estado' => PublicacionEnvio::PENDIENTE,
            'intentos' => 0,
            'proximo_intento_at' => null,
        ], $overrides));
    }

    protected function fakeEspera(): EsperaFalsa
    {
        $falsa = new EsperaFalsa();
        $this->app->instance(Espera::class, $falsa);

        return $falsa;
    }

    protected function fakeHttpOk(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 1],
            ], 200),
            'discord.com/*' => Http::response('', 204),
        ]);
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
