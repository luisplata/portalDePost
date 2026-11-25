<?php

namespace Tests\Feature\Publicacion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Publicacion\PublicacionTestSupport;

/**
 * Prueba de canal sin tocar el catalogo (T-S5-08, REQ-CAN-11).
 */
class ProbarPublicacionTest extends PublicacionTestSupport
{
    use RefreshDatabase;

    public function test_probar_sends_fixed_text_without_creating_deliveries()
    {
        Http::fake(['api.telegram.org/*' => Http::response([
            'ok' => true,
            'result' => ['message_id' => 1],
        ], 200)]);
        $canal = $this->makeCanal('telegram');
        $this->fakeEspera();

        $this->artisan('publicaciones:probar', ['--canal' => $canal->id])->assertExitCode(0);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return strpos($request->url(), '/sendMessage') !== false
                && strpos($request->data()['text'], 'Prueba de publicacion') !== false;
        });
        $this->assertSame(0, DB::table('publicacion_envios')->count());
        $this->assertSame('ok', $canal->fresh()->estado);
        $this->assertNotNull($canal->fresh()->estado_verificado_at);
    }

    public function test_probar_accepts_the_channel_name()
    {
        Http::fake(['api.telegram.org/*' => Http::response([
            'ok' => true,
            'result' => ['message_id' => 1],
        ], 200)]);
        $canal = $this->makeCanal('telegram', ['nombre' => 'Telegram principal']);
        $this->fakeEspera();

        $this->artisan('publicaciones:probar', ['--canal' => 'Telegram principal'])
            ->assertExitCode(0);

        Http::assertSentCount(1);
    }

    public function test_probar_401_marks_credentials_invalid_without_deliveries()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 401)]);
        $canal = $this->makeCanal('telegram');
        $this->fakeEspera();

        $this->artisan('publicaciones:probar', ['--canal' => $canal->id])->assertExitCode(1);

        $this->assertSame('invalido_credencial', $canal->fresh()->estado);
        $this->assertSame(0, DB::table('publicacion_envios')->count());
    }

    public function test_probar_with_an_unknown_channel_exits_one()
    {
        Http::fake();

        $this->artisan('publicaciones:probar', ['--canal' => 'inexistente'])->assertExitCode(1);

        Http::assertNothingSent();
    }
}
