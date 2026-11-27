<?php

namespace Tests\Feature\Publicacion;

use App\Services\Publicacion\PublicacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Fallos de entrega: backoff, 429, incierto y reintento manual
 * (T-S4-07..10, REQ-ENT-06..09, ENT-13, DRV-10).
 */
class EnvioFallosTest extends PublicacionTestSupport
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_server_failure_backoff_is_1_5_15_60_and_fifth_fails()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 500)]);
        $this->makeAjustes();
        $canal = $this->makeCanal('telegram');
        $producto = $this->makeProducto();
        $envio = $this->makeEnvio($producto, $canal->id);
        $this->fakeEspera();
        $servicio = $this->app->make(PublicacionService::class);

        $esperados = [
            ['2026-11-25 10:00:00', 1, '2026-11-25 10:01:00'],
            ['2026-11-25 10:02:00', 2, '2026-11-25 10:07:00'],
            ['2026-11-25 10:08:00', 3, '2026-11-25 10:23:00'],
            ['2026-11-25 10:24:00', 4, '2026-11-25 11:24:00'],
        ];

        foreach ($esperados as $caso) {
            Carbon::setTestNow($caso[0]);
            $servicio->corrida();

            $this->assertSame($caso[1], $envio->fresh()->intentos, $caso[0]);
            $this->assertSame('pendiente', $envio->fresh()->estado);
            $this->assertSame($caso[2], $envio->fresh()->proximo_intento_at->toDateTimeString());
        }

        Carbon::setTestNow('2026-11-25 11:25:00');
        $servicio->corrida();

        $this->assertSame(5, $envio->fresh()->intentos);
        $this->assertSame('fallido', $envio->fresh()->estado);
        $this->assertNull($envio->fresh()->proximo_intento_at);

        Carbon::setTestNow('2026-11-26 12:00:00');
        $servicio->corrida();
        Http::assertSentCount(5);
    }

    public function test_429_keeps_attempts_and_sets_next_attempt_with_retry_after()
    {
        $respuestas = [
            Http::response(['ok' => false, 'parameters' => ['retry_after' => 30]], 429),
            Http::response(['ok' => false], 429),
            Http::response(['ok' => false, 'parameters' => ['retry_after' => 5000]], 429),
        ];
        $indice = 0;
        Http::fake(function () use (&$indice, $respuestas) {
            return $respuestas[$indice++];
        });

        $this->makeAjustes();
        $canal = $this->makeCanal('telegram');
        $envio = $this->makeEnvio($this->makeProducto(), $canal->id);
        $this->fakeEspera();
        $servicio = $this->app->make(PublicacionService::class);

        $casos = [
            ['2026-11-25 10:00:00', '2026-11-25 10:00:30'],
            ['2026-11-25 10:01:00', '2026-11-25 10:02:00'],
            ['2026-11-25 10:02:01', '2026-11-25 10:17:01'],
        ];

        foreach ($casos as $caso) {
            Carbon::setTestNow($caso[0]);
            $servicio->corrida();

            $this->assertSame(0, $envio->fresh()->intentos);
            $this->assertSame('pendiente', $envio->fresh()->estado);
            $this->assertSame($caso[1], $envio->fresh()->proximo_intento_at->toDateTimeString());
            $this->assertSame(429, $envio->fresh()->http_status);
        }
    }

    public function test_ambiguous_timeout_never_retries_automatically()
    {
        $intentos = 0;
        Http::fake(function () use (&$intentos) {
            $intentos++;
            throw $this->timeoutDeLectura();
        });

        $this->makeAjustes();
        $canal = $this->makeCanal('telegram');
        $envio = $this->makeEnvio($this->makeProducto(), $canal->id);
        $this->fakeEspera();
        $servicio = $this->app->make(PublicacionService::class);

        Carbon::setTestNow('2026-11-25 10:00:00');
        $servicio->corrida();

        $this->assertSame('incierto', $envio->fresh()->estado);
        $this->assertSame(1, $envio->fresh()->intentos);
        $this->assertNull($envio->fresh()->proximo_intento_at);

        foreach (['2026-11-26 10:00:00', '2026-11-27 10:00:00'] as $dia) {
            Carbon::setTestNow($dia);
            $servicio->corrida();
        }

        $this->assertSame('incierto', $envio->fresh()->estado);
        $this->assertSame(1, $intentos);
    }

    public function test_a_broken_channel_does_not_block_the_other()
    {
        $llamadas = 0;
        Http::fake(function ($request) use (&$llamadas) {
            $llamadas++;

            if (strpos($request->url(), 'api.telegram.org') !== false) {
                throw $this->timeoutDeLectura();
            }

            return Http::response('', 204);
        });

        $this->makeAjustes();
        $telegram = $this->makeCanal('telegram');
        $discord = $this->makeCanal('discord');
        $producto = $this->makeProducto();
        $envioTelegram = $this->makeEnvio($producto, $telegram->id);
        $envioDiscord = $this->makeEnvio($producto, $discord->id, ['canal_tipo' => 'discord']);
        $this->fakeEspera();

        $this->app->make(PublicacionService::class)->corrida();

        $this->assertSame('incierto', $envioTelegram->fresh()->estado);
        $this->assertSame('enviado', $envioDiscord->fresh()->estado);
        $this->assertSame(2, $llamadas);
    }

    public function test_manual_retry_from_failed_uses_one_inline_call()
    {
        Http::fake(['api.telegram.org/*' => Http::response([
            'ok' => true,
            'result' => ['message_id' => 5],
        ], 200)]);

        $this->makeAjustes();
        $canal = $this->makeCanal('telegram');
        $producto = $this->makeProducto();
        $envio = $this->makeEnvio($producto, $canal->id, [
            'estado' => 'fallido',
            'intentos' => 5,
        ]);
        $this->fakeEspera();

        $resultado = $this->app->make(PublicacionService::class)->reintentar($envio);

        $this->assertTrue($resultado['ok']);
        $this->assertSame('enviado', $envio->fresh()->estado);
        $this->assertSame('5', $envio->fresh()->referencia_externa);
        $this->assertSame(1, (int) DB::table('productos')->where('id', $producto)->value('published'));
        Http::assertSentCount(1);
    }

    public function test_manual_retry_rejects_a_pending_delivery()
    {
        $this->fakeHttpOk();
        $this->makeAjustes();
        $envio = $this->makeEnvio($this->makeProducto(), $this->makeCanal('telegram')->id);
        $this->fakeEspera();

        $resultado = $this->app->make(PublicacionService::class)->reintentar($envio);

        $this->assertFalse($resultado['ok']);
        $this->assertSame('La entrega ya esta pendiente.', $resultado['mensaje']);
        Http::assertNothingSent();
    }

    public function test_manual_retry_requires_an_operative_channel()
    {
        $this->fakeHttpOk();
        $this->makeAjustes();
        $canal = $this->makeCanal('telegram', ['habilitado' => 0]);
        $envio = $this->makeEnvio($this->makeProducto(), $canal->id, ['estado' => 'fallido']);
        $this->fakeEspera();

        $resultado = $this->app->make(PublicacionService::class)->reintentar($envio);

        $this->assertFalse($resultado['ok']);
        $this->assertSame('El canal no esta disponible para enviar.', $resultado['mensaje']);
        Http::assertNothingSent();
    }

    private function timeoutDeLectura(): \Illuminate\Http\Client\ConnectionException
    {
        return new \Illuminate\Http\Client\ConnectionException(
            'cURL error 28: Operation timed out',
            0,
            new \GuzzleHttp\Exception\ConnectException(
                'cURL error 28: Operation timed out',
                new \GuzzleHttp\Psr7\Request('POST', 'https://api.telegram.org'),
                null,
                ['errno' => 28, 'pretransfer_time' => 0.42]
            )
        );
    }
}
