<?php

namespace Tests\Feature\Publicacion;

use App\Services\Publicacion\PublicacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Publicacion\PublicacionTestSupport;

/**
 * Sembrado idempotente y corte efectivo (T-S4-03/04, REQ-ENT-01, ENT-02,
 * ENT-03, ENT-12).
 */
class SembradoTest extends PublicacionTestSupport
{
    use RefreshDatabase;

    public function test_eligible_product_creates_one_pending_delivery()
    {
        $this->fakeHttpOk();
        $this->makeAjustes();
        $canal = $this->makeCanal('telegram');
        $producto = $this->makeProducto();
        $this->fakeEspera();

        $resultado = $this->app->make(PublicacionService::class)->corrida();

        $this->assertSame('ok', $resultado['estado']);
        $this->assertSame(1, $resultado['sembradas']);
        $this->assertSame(1, $resultado['enviadas']);
        $this->assertSame(1, DB::table('publicacion_envios')
            ->where('producto_id', $producto)
            ->where('publicacion_canal_id', $canal->id)
            ->count());
        $this->assertSame(1, (int) DB::table('productos')->where('id', $producto)->value('published'));
    }

    public function test_inactive_future_and_pre_cutoff_products_are_not_seeded()
    {
        $this->fakeHttpOk();
        $this->makeAjustes([
            'publicaciones_desde' => now()->subDays(10)->toDateTimeString(),
        ]);
        $this->makeCanal('telegram');
        $this->makeProducto(['estado' => 0]);
        $this->makeProducto(['publication_date' => now()->addDay()]);
        $this->makeProducto(['created_at' => now()->subDays(30), 'publication_date' => now()->subDays(30)]);

        $this->fakeEspera();
        $resultado = $this->app->make(PublicacionService::class)->corrida();

        $this->assertSame(0, $resultado['sembradas']);
        $this->assertSame(0, DB::table('publicacion_envios')->count());
    }

    public function test_three_runs_keep_a_single_delivery_per_pair()
    {
        $this->fakeHttpOk();
        $this->makeAjustes();
        $this->makeCanal('telegram');
        $producto = $this->makeProducto();
        $this->fakeEspera();

        for ($i = 0; $i < 3; $i++) {
            $this->app->make(PublicacionService::class)->corrida();
        }

        $this->assertSame(1, DB::table('publicacion_envios')->where('producto_id', $producto)->count());
    }

    public function test_a_new_channel_does_not_drag_the_thirty_day_history()
    {
        $this->fakeHttpOk();
        $this->makeAjustes([
            'publicaciones_desde' => now()->subDays(60)->toDateTimeString(),
        ]);
        $this->fakeEspera();

        $viejo = $this->makeProducto([
            'created_at' => now()->subDays(30),
            'publication_date' => now()->subDays(30),
        ]);
        $reciente = $this->makeProducto([
            'created_at' => now()->subDay(),
            'publication_date' => now()->subHours(2),
        ]);

        $canalNuevo = $this->makeCanal('telegram', ['vigente_desde' => now()]);

        $this->app->make(PublicacionService::class)->corrida();

        $this->assertSame(0, DB::table('publicacion_envios')
            ->where('publicacion_canal_id', $canalNuevo->id)->count());

        $retroactivo = $this->makeCanal('telegram', ['vigente_desde' => now()->subDays(40)]);

        $this->app->make(PublicacionService::class)->corrida();

        $this->assertSame(0, DB::table('publicacion_envios')
            ->where('producto_id', $viejo)->where('publicacion_canal_id', $retroactivo->id)->count());
        $this->assertSame(1, DB::table('publicacion_envios')
            ->where('producto_id', $reciente)->where('publicacion_canal_id', $retroactivo->id)->count());
    }
}
