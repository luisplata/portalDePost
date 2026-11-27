<?php

namespace Tests\Feature\Publicacion;

use App\Services\Publicacion\Espera;
use App\Services\Publicacion\PublicacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use App\PublicacionCanal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Tests\Feature\Publicacion\PublicacionTestSupport;

/**
 * Comando productos:procesar: dry-run, limite y lock (T-S5-06/07, REQ-ENT-14,
 * ENT-15, NFR-08, NFR-09).
 */
class ComandoTest extends PublicacionTestSupport
{
    use RefreshDatabase;

    private const TOKEN = '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw';

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

    public function test_dry_run_does_not_write_anything()
    {
        Http::fake();
        $this->makeAjustes();
        $this->makeCanal('telegram');
        PublicacionCanal::create([
            'tipo' => 'mastodon',
            'nombre' => 'Sin driver',
            'habilitado' => 1,
            'vigente_desde' => now()->subDay(),
            'credenciales' => ['token' => 'x', 'chat_id' => 'y'],
            'estado' => 'ok',
        ]);
        $ilegible = PublicacionCanal::create([
            'tipo' => 'telegram',
            'nombre' => 'Clave ajena',
            'habilitado' => 1,
            'vigente_desde' => now()->subDay(),
            'credenciales' => ['token' => self::TOKEN, 'chat_id' => '-1'],
            'estado' => 'ok',
        ]);
        $otraClave = new \Illuminate\Encryption\Encrypter(random_bytes(32), 'AES-256-CBC');
        DB::table('publicacion_canales')->where('id', $ilegible->id)->update([
            'credenciales' => $otraClave->encrypt(json_encode(['token' => self::TOKEN, 'chat_id' => '-1'])),
        ]);
        $this->makeProducto();
        $this->fakeEspera();

        $antes = $this->snapshot();

        $this->artisan('productos:procesar', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame($antes, $this->snapshot());
        Http::assertNothingSent();
    }

    public function test_dry_run_lists_the_effective_cutoff_and_pending_deliveries()
    {
        Carbon::setTestNow('2026-11-25 10:00:00');
        Http::fake();
        $this->makeAjustes(['publicaciones_desde' => '2026-11-10 00:00:00']);
        $canal = $this->makeCanal('telegram', [
            'nombre' => 'Canal plan',
            'vigente_desde' => '2026-11-15 00:00:00',
        ]);
        $this->fakeEspera();

        foreach ([0, 1] as $i) {
            $this->makeProducto([
                'created_at' => now()->subDays(2),
                'publication_date' => now()->subHours(2),
            ]);
        }

        $pendiente = $this->makeProducto([
            'created_at' => now()->subDays(2),
            'publication_date' => now()->subHours(3),
        ]);
        $this->makeEnvio($pendiente, $canal->id);

        \Illuminate\Support\Facades\Artisan::call('productos:procesar', ['--dry-run' => true]);
        $salida = \Illuminate\Support\Facades\Artisan::output();

        $this->assertStringContainsString('Canal plan', $salida);
        $this->assertStringContainsString('corte efectivo 2026-11-15 00:00:00', $salida);
        $this->assertStringContainsString('sembraria 2', $salida);
        $this->assertStringContainsString('/redirect/'.$pendiente, $salida);
    }

    private function snapshot(): array
    {
        return [
            'canales' => json_encode(DB::table('publicacion_canales')->orderBy('id')->get()),
            'envios' => json_encode(DB::table('publicacion_envios')->orderBy('id')->get()),
            'productos' => json_encode(DB::table('productos')->orderBy('id')->get(['id', 'published'])),
            'ajustes' => json_encode(DB::table('publicacion_ajustes')->orderBy('clave')->get()),
        ];
    }
}
