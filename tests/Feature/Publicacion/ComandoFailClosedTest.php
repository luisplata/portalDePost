<?php

namespace Tests\Feature\Publicacion;

use App\PublicacionAjuste;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Publicacion\PublicacionTestSupport;

/**
 * Fail-closed del comando (T-S5-05, REQ-AJUS-02, AJUS-03, AJUS-06).
 *
 * Sin RefreshDatabase: el test de fallo de lectura hace DDL y lo restaura.
 */
class ComandoFailClosedTest extends PublicacionTestSupport
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);

        parent::tearDown();
    }

    public function test_disabled_switch_writes_nothing_and_exits_zero()
    {
        Http::fake();
        $this->makeAjustes([PublicacionAjuste::CLAVE_ACTIVO => '0']);
        $this->makeCanal('telegram');
        $this->makeProducto();
        $this->fakeEspera();

        $this->artisan('productos:procesar')->assertExitCode(0);

        $this->assertSame(0, \DB::table('publicacion_envios')->count());
        Http::assertNothingSent();
    }

    public function test_missing_cutoff_exits_one()
    {
        Http::fake();
        $this->makeAjustes([
            PublicacionAjuste::CLAVE_ACTIVO => '1',
            PublicacionAjuste::CLAVE_DESDE => null,
        ]);
        $this->makeCanal('telegram');
        $this->makeProducto();
        $this->fakeEspera();

        $this->artisan('productos:procesar')->assertExitCode(1);

        $this->assertSame(0, \DB::table('publicacion_envios')->count());
        Http::assertNothingSent();
    }

    public function test_a_settings_read_failure_exits_one()
    {
        Http::fake();
        $this->makeCanal('telegram');
        $this->makeProducto();
        $this->fakeEspera();

        Schema::rename('publicacion_ajustes', 'publicacion_ajustes_roto');

        try {
            $this->artisan('productos:procesar')->assertExitCode(1);
            $this->assertSame(0, \DB::table('publicacion_envios')->count());
            Http::assertNothingSent();
        } finally {
            Schema::rename('publicacion_ajustes_roto', 'publicacion_ajustes');
        }
    }

}
