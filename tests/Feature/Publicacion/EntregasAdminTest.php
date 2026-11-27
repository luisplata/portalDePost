<?php

namespace Tests\Feature\Publicacion;

use App\PublicacionEnvio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Publicacion\PublicacionTestSupport;

/**
 * Admin de entregas: filtros, paginacion, error redactado y reintento
 * (T-S7a-01..04, REQ-OBS-01..04, OBS-09, NFR-07).
 */
class EntregasAdminTest extends PublicacionTestSupport
{
    use RefreshDatabase;

    public function test_deliveries_routes_require_session()
    {
        $this->get('/admin/publicaciones')->assertRedirect('/logout');
        $this->post('/admin/publicaciones/1/reintentar')->assertRedirect('/logout');
    }

    public function test_index_paginates_twenty_and_ignores_invalid_filters()
    {
        $canal = $this->makeCanal('telegram');
        for ($i = 0; $i < 25; $i++) {
            $this->makeEnvio($this->makeProducto(), $canal->id);
        }

        $respuesta = $this->asAdmin()->get('/admin/publicaciones?estado=hack&canal=abc&producto=x&fecha=ayer&page=2');

        $respuesta->assertStatus(200);
        $this->assertCount(5, $respuesta->viewData('envios'));
        $respuesta->assertSee('name="_token"', false);
        $respuesta->assertSee('name="estado"', false);
        $respuesta->assertSee('name="canal"', false);
        $respuesta->assertSee('name="producto"', false);
        $respuesta->assertSee('name="fecha"', false);
    }

    public function test_index_filters_by_estado_canal_producto_and_fecha()
    {
        $canal = $this->makeCanal('telegram');
        $otroCanal = $this->makeCanal('discord');
        $producto = $this->makeProducto();
        $otroProducto = $this->makeProducto();

        $otroProductoDos = $this->makeProducto();

        $this->makeEnvio($producto, $canal->id, ['estado' => 'fallido', 'error' => 'boom']);
        $this->makeEnvio($otroProducto, $canal->id, ['estado' => 'enviado']);
        $this->makeEnvio($otroProductoDos, $otroCanal->id, ['estado' => 'fallido', 'canal_tipo' => 'discord']);

        $respuesta = $this->asAdmin()->get('/admin/publicaciones?estado=fallido&canal='.$canal->id);

        $respuesta->assertStatus(200);
        $this->assertCount(1, $respuesta->viewData('envios'));
        $respuesta->assertSee('boom');

        $porProducto = $this->asAdmin()->get('/admin/publicaciones?producto='.$producto);
        $this->assertCount(1, $porProducto->viewData('envios'));

        $porFecha = $this->asAdmin()->get('/admin/publicaciones?fecha='.now()->format('Y-m-d'));
        $this->assertCount(3, $porFecha->viewData('envios'));

        $porFechaVieja = $this->asAdmin()->get('/admin/publicaciones?fecha=2020-01-01');
        $this->assertCount(0, $porFechaVieja->viewData('envios'));
    }

    public function test_index_redacts_and_truncates_the_error()
    {
        $canal = $this->makeCanal('telegram');
        $producto = $this->makeProducto();
        $this->makeEnvio($producto, $canal->id, [
            'estado' => 'fallido',
            'error' => str_repeat('x', 900),
        ]);

        $respuesta = $this->asAdmin()->get('/admin/publicaciones');

        $respuesta->assertStatus(200);
        $respuesta->assertDontSee(str_repeat('x', 600));
    }

    public function test_retry_from_failed_sends_once_and_updates_the_delivery()
    {
        Http::fake(['api.telegram.org/*' => Http::response([
            'ok' => true,
            'result' => ['message_id' => 3],
        ], 200)]);
        $this->makeAjustes();
        $canal = $this->makeCanal('telegram');
        $envio = $this->makeEnvio($this->makeProducto(), $canal->id, [
            'estado' => 'fallido',
            'intentos' => 5,
        ]);
        $this->fakeEspera();

        $this->asAdmin()->post('/admin/publicaciones/'.$envio->id.'/reintentar')
            ->assertRedirect(url('admin/publicaciones'));

        $this->assertSame('enviado', $envio->fresh()->estado);
        Http::assertSentCount(1);
    }

    public function test_retry_rejects_a_pending_delivery_without_calling_http()
    {
        Http::fake();
        $envio = $this->makeEnvio($this->makeProducto(), $this->makeCanal('telegram')->id);
        $this->fakeEspera();

        $respuesta = $this->asAdmin()->post('/admin/publicaciones/'.$envio->id.'/reintentar');

        $respuesta->assertRedirect(url('admin/publicaciones'));
        $respuesta->assertSessionHas('error', 'La entrega ya esta pendiente.');
        Http::assertNothingSent();
    }

    public function test_retry_of_a_non_operative_channel_does_not_call_http()
    {
        Http::fake();
        $canal = $this->makeCanal('telegram', ['habilitado' => 0]);
        $envio = $this->makeEnvio($this->makeProducto(), $canal->id, ['estado' => 'fallido']);
        $this->fakeEspera();

        $respuesta = $this->asAdmin()->post('/admin/publicaciones/'.$envio->id.'/reintentar');

        $respuesta->assertSessionHas('error', 'El canal no esta disponible para enviar.');
        Http::assertNothingSent();
    }

    public function test_index_warns_about_duplicates_only_for_uncertain_or_sent()
    {
        $producto = $this->makeProducto();
        $this->makeEnvio($producto, $this->makeCanal('telegram')->id, ['estado' => 'incierto', 'error' => 'timeout']);
        $this->makeEnvio($producto, $this->makeCanal('telegram')->id, ['estado' => 'enviado']);
        $this->makeEnvio($producto, $this->makeCanal('telegram')->id, ['estado' => 'fallido']);

        $respuesta = $this->asAdmin()->get('/admin/publicaciones');

        $respuesta->assertStatus(200);
        $respuesta->assertSee('posible duplicado');
        $respuesta->assertSee('Reintentar');
    }
}
