<?php

namespace Tests\Feature\Publicacion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Publicacion\PublicacionTestSupport;

/**
 * Columna Redes del listado de productos (T-S7a-05..07, REQ-OBS-05, OBS-06,
 * REG-05, REG-08, NFR-07).
 */
class DashboardRedesTest extends PublicacionTestSupport
{
    use RefreshDatabase;

    public function test_dashboard_shows_redes_status_without_n_plus_one()
    {
        $telegram = $this->makeCanal('telegram');
        $discord = $this->makeCanal('discord');
        $producto = $this->makeProducto(['nombre' => 'Con redes']);
        $this->makeEnvio($producto, $telegram->id, ['estado' => 'enviado']);
        $this->makeEnvio($producto, $discord->id, [
            'estado' => 'fallido',
            'canal_tipo' => 'discord',
            'error' => 'boom',
        ]);

        $respuesta = $this->asAdmin()->get('/admin/producto');

        $respuesta->assertStatus(200);
        $respuesta->assertSee('TG ok / DC fallo');
        $respuesta->assertSee('boom');
        $respuesta->assertSee('Visible en la web?');
        $respuesta->assertDontSee('Es publicado?');
    }

    public function test_dashboard_shows_a_dash_without_deliveries()
    {
        $this->makeProducto(['nombre' => 'Sin redes']);

        $respuesta = $this->asAdmin()->get('/admin/producto');

        $respuesta->assertStatus(200);
        $respuesta->assertSee('Sin redes');
        $respuesta->assertSeeInOrder(['Sin redes', '-'], false);
    }

    public function test_redes_query_count_does_not_grow_with_products()
    {
        $canal = $this->makeCanal('telegram');

        for ($i = 0; $i < 5; $i++) {
            $producto = $this->makeProducto();
            $this->makeEnvio($producto, $canal->id);
        }

        $consultas = 0;
        DB::listen(function () use (&$consultas) {
            $consultas++;
        });

        $this->asAdmin()->get('/admin/producto')->assertStatus(200);
        $conCinco = $consultas;

        for ($i = 0; $i < 45; $i++) {
            $producto = $this->makeProducto();
            $this->makeEnvio($producto, $canal->id);
        }

        $consultas = 0;
        $this->asAdmin()->get('/admin/producto')->assertStatus(200);

        $this->assertLessThanOrEqual($conCinco + 2, $consultas);
    }
}
