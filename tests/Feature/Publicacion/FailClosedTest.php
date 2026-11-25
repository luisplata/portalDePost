<?php

namespace Tests\Feature\Publicacion;

use App\PublicacionAjuste;
use App\Services\Publicacion\PublicacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Fail-closed de ajustes y canales (T-S4-04, REQ-AJUS-01, AJUS-02, AJUS-03,
 * AJUS-06).
 */
class FailClosedTest extends PublicacionTestSupport
{
    use RefreshDatabase;

    public function test_disabled_switch_writes_nothing_and_exits_zero()
    {
        $this->fakeHttpOk();
        $this->makeAjustes([PublicacionAjuste::CLAVE_ACTIVO => '0']);
        $this->makeCanal('telegram');
        $this->makeProducto();
        $this->fakeEspera();

        $resultado = $this->app->make(PublicacionService::class)->corrida();

        $this->assertSame('apagado', $resultado['estado']);
        $this->assertSame(0, $resultado['exit']);
        $this->assertSame(0, DB::table('publicacion_envios')->count());
        $this->assertSame(0, DB::table('productos')->where('published', 1)->count());
        Http::assertNothingSent();
    }

    public function test_missing_cutoff_is_invalid_and_exits_one()
    {
        $this->fakeHttpOk();
        $this->makeAjustes([
            PublicacionAjuste::CLAVE_ACTIVO => '1',
            PublicacionAjuste::CLAVE_DESDE => null,
        ]);
        $this->makeCanal('telegram');
        $this->makeProducto();
        $this->fakeEspera();

        $resultado = $this->app->make(PublicacionService::class)->corrida();

        $this->assertSame('corte_invalido', $resultado['estado']);
        $this->assertSame(1, $resultado['exit']);
        $this->assertSame(0, DB::table('publicacion_envios')->count());
        Http::assertNothingSent();
    }

    public function test_an_invalid_cutoff_string_is_invalid()
    {
        $this->fakeHttpOk();
        $this->makeAjustes([PublicacionAjuste::CLAVE_DESDE => 'ayer']);
        $this->makeCanal('telegram');
        $this->makeProducto();
        $this->fakeEspera();

        $resultado = $this->app->make(PublicacionService::class)->corrida();

        $this->assertSame('corte_invalido', $resultado['estado']);
        $this->assertSame(1, $resultado['exit']);
    }

    public function test_without_operative_channels_it_completes_with_a_zero_summary()
    {
        $this->fakeHttpOk();
        $this->makeAjustes();
        $this->makeCanal('telegram', ['habilitado' => 0]);
        $this->makeProducto();
        $this->fakeEspera();

        $resultado = $this->app->make(PublicacionService::class)->corrida();

        $this->assertSame('sin_canales', $resultado['estado']);
        $this->assertSame(0, $resultado['exit']);
        $this->assertSame(0, DB::table('publicacion_envios')->count());

        $resumen = PublicacionAjuste::resumenCorrida();
        $this->assertNotNull($resumen['corrida_at']);
        $this->assertSame(0, $resumen['sembradas']);
        $this->assertSame(0, $resumen['enviadas']);
        Http::assertNothingSent();
    }
}
