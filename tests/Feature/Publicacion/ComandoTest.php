<?php

namespace Tests\Feature\Publicacion;

use App\Services\Publicacion\Espera;
use App\Services\Publicacion\PublicacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Publicacion\PublicacionTestSupport;

/**
 * Comando productos:procesar: dry-run, limite y lock (T-S5-06/07, REQ-ENT-14,
 * ENT-15, NFR-08, NFR-09).
 */
class ComandoTest extends PublicacionTestSupport
{
    use RefreshDatabase;

    public function test_dry_run_writes_nothing_and_calls_no_api()
    {
        Http::fake();
        $this->makeAjustes();
        $this->makeCanal('telegram');
        for ($i = 0; $i < 3; $i++) {
            $this->makeProducto();
        }
        $this->fakeEspera();

        $this->artisan('productos:procesar', ['--dry-run' => true])
            ->expectsOutput('Sembraria 3 | Enviaria 0 | Fallidas 0 | Inciertas 0')
            ->assertExitCode(0);

        $this->assertSame(0, DB::table('publicacion_envios')->count());
        $this->assertSame(0, DB::table('productos')->where('published', 1)->count());
        Http::assertNothingSent();
    }

    public function test_limit_caps_the_total_of_the_run()
    {
        $this->fakeHttpOk();
        $this->makeAjustes(['publicaciones_desde' => now()->subDays(30)->toDateTimeString()]);
        $this->makeCanal('telegram', ['vigente_desde' => now()->subDays(30)]);
        for ($i = 0; $i < 30; $i++) {
            $this->makeProducto([
                'created_at' => now()->subDay(),
                'publication_date' => now()->subHours(2),
            ]);
        }
        $this->fakeEspera();

        $this->artisan('productos:procesar', ['--limit' => 5])->assertExitCode(0);

        $this->assertSame(5, DB::table('publicacion_envios')->where('estado', 'enviado')->count());
        $this->assertSame(25, DB::table('publicacion_envios')->where('estado', 'pendiente')->count());
        Http::assertSentCount(5);
    }

    public function test_limit_zero_exits_one_without_effects()
    {
        Http::fake();
        $this->makeAjustes();
        $this->makeCanal('telegram');
        $this->makeProducto();
        $this->fakeEspera();

        $this->artisan('productos:procesar', ['--limit' => 0])->assertExitCode(1);

        $this->assertSame(0, DB::table('publicacion_envios')->count());
        Http::assertNothingSent();
    }

    public function test_lock_held_returns_zero_and_logs()
    {
        Http::fake();
        $this->makeAjustes();
        $this->makeCanal('telegram');
        $this->makeProducto();
        $this->fakeEspera();

        $lock = Cache::lock('publicaciones:corrida', 1800);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('productos:procesar')
                ->expectsOutput('Corrida en curso; sin cambios.')
                ->assertExitCode(0);
        } finally {
            $lock->release();
        }

        $this->assertSame(0, DB::table('publicacion_envios')->count());
        Http::assertNothingSent();
    }

    public function test_an_exception_releases_the_lock()
    {
        Http::fake();
        $this->makeAjustes();
        $this->makeCanal('telegram');
        $this->makeProducto();

        $this->app->instance(PublicacionService::class, new class($this->app->make(Espera::class)) extends PublicacionService {
            public function corrida(array $opciones = []): array
            {
                throw new \RuntimeException('boom');
            }
        });

        try {
            $this->artisan('productos:procesar');
            $this->fail('Se esperaba la excepcion del servicio.');
        } catch (\RuntimeException $error) {
            $this->assertSame('boom', $error->getMessage());
        }

        $lock = Cache::lock('publicaciones:corrida', 1800);
        $this->assertTrue($lock->get(), 'El lock debe quedar libre tras la excepcion.');
        $lock->release();
    }
}
