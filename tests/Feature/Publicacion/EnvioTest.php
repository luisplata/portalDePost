<?php

namespace Tests\Feature\Publicacion;

use App\Services\Publicacion\PublicacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Envio: orden, lote y pausa (T-S4-06, REQ-ENT-04, ENT-05, ENT-13).
 */
class EnvioTest extends PublicacionTestSupport
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dispatch_order_is_publication_date_then_id()
    {
        Carbon::setTestNow('2026-11-25 10:00:00');
        $this->fakeHttpOk();
        $this->makeAjustes();
        $canal = $this->makeCanal('telegram');
        $this->fakeEspera();

        $ids = [];
        foreach ([10, 11, 12] as $dias) {
            $id = $this->makeProducto([
                'publication_date' => now()->subDays($dias),
                'created_at' => now()->subDays(20),
            ]);
            $this->makeEnvio($id, $canal->id);
            $ids[] = $id;
        }

        $this->app->make(PublicacionService::class)->corrida();

        $orden = Http::recorded()->map(function ($par) {
            preg_match('#/redirect/([0-9]+)#', $par[0]->data()['caption'], $m);

            return (int) $m[1];
        })->all();

        $this->assertSame([$ids[2], $ids[1], $ids[0]], $orden);
    }

    public function test_lote_max_sends_ten_and_pauses_between_sends()
    {
        $this->fakeHttpOk();
        $this->makeAjustes(['publicaciones_desde' => now()->subDays(30)->toDateTimeString()]);
        $canal = $this->makeCanal('telegram', ['vigente_desde' => now()->subDays(30)]);
        $espera = $this->fakeEspera();

        for ($i = 0; $i < 15; $i++) {
            $id = $this->makeProducto([
                'publication_date' => now()->subHours(2),
                'created_at' => now()->subDay(),
            ]);
            $this->makeEnvio($id, $canal->id);
        }

        $this->app->make(PublicacionService::class)->corrida();

        $this->assertSame(10, DB::table('publicacion_envios')->where('estado', 'enviado')->count());
        $this->assertSame(5, DB::table('publicacion_envios')->where('estado', 'pendiente')->count());
        $this->assertSame(array_fill(0, 9, 800), $espera->llamadas);
        Http::assertSentCount(10);
    }

    public function test_a_non_operative_channel_is_skipped_without_attempts()
    {
        $this->fakeHttpOk();
        $this->makeAjustes();
        $sano = $this->makeCanal('telegram');
        $roto = $this->makeCanal('discord', ['estado' => 'invalido_credencial']);
        $producto = $this->makeProducto();
        $this->makeEnvio($producto, $sano->id);
        $envioRoto = $this->makeEnvio($producto, $roto->id, ['canal_tipo' => 'discord']);
        $this->fakeEspera();

        $this->app->make(PublicacionService::class)->corrida();

        Http::assertSentCount(1);
        $this->assertSame('pendiente', $envioRoto->fresh()->estado);
        $this->assertSame(0, $envioRoto->fresh()->intentos);
    }
}
