<?php

namespace Tests\Feature\Analytics;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Caracterizacion del admin existente y de las graficas legacy (REG-06).
 *
 * Sin sesion, el grupo `logeado` redirige a `/logout`. Con sesion, las
 * pantallas de producto, stream, categoria, graficas y publicidad responden
 * 200, y las cargas CSV siguen alcanzables. Las APIs de graficas conservan
 * su shape.
 */
class AdminRegressionTest extends AnalyticsTestSupport
{
    use RefreshDatabase;

    private const ADMIN_ROUTES = [
        '/admin/producto',
        '/admin/stream',
        '/admin/categoria',
        '/admin/graficas',
        '/admin/publicidad',
    ];

    public function test_admin_routes_redirect_to_logout_without_a_session()
    {
        foreach (self::ADMIN_ROUTES as $route) {
            $this->get($route)->assertRedirect('/logout');
        }

        $this->get('/admin/producto/upload')->assertRedirect('/logout');
        $this->get('/admin/stream/upload')->assertRedirect('/logout');
    }

    public function test_admin_routes_render_with_a_session()
    {
        foreach (self::ADMIN_ROUTES as $route) {
            $this->asAdmin()->get($route)->assertStatus(200);
        }

        $this->asAdmin()->get('/admin/producto/upload')->assertStatus(200);
        $this->asAdmin()->get('/admin/stream/upload')->assertStatus(200);
    }

    public function test_legacy_graphic_api_keeps_its_shape()
    {
        $productoId = $this->makeProduct();
        $this->makeLogVisit($productoId);

        $response = $this->getJson('/api/grafica')->assertStatus(200);

        $items = $response->json();

        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        $this->assertSame(['count_of_day', 'date_of_day'], $this->keysOf($items[0]));
    }

    public function test_legacy_conversion_api_keeps_its_shape()
    {
        $productoId = $this->makeProduct(['nombre' => 'Grafica Uno']);
        $this->makeVisitPost($productoId, 10, 2);

        $response = $this->getJson('/api/TazaDeConvercion')->assertStatus(200);

        $items = $response->json();

        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        $keys = $this->keysOf($items[0]);
        foreach (['id', 'nombre', 'visitas', 'clicks', 'porcentaje_efectividad'] as $key) {
            $this->assertContains($key, $keys);
        }
    }

    public function test_legacy_visits_vs_clicks_api_keeps_its_shape()
    {
        $productoId = $this->makeProduct(['nombre' => 'Grafica Dos']);
        $this->makeVisitPost($productoId, 10, 2);

        $response = $this->getJson('/api/VisitsVsClicks')->assertStatus(200);

        $items = $response->json();

        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        $keys = $this->keysOf($items[0]);
        foreach (['id', 'nombre', 'visitas', 'clicks'] as $key) {
            $this->assertContains($key, $keys);
        }
        $this->assertNotContains('porcentaje_efectividad', $keys);
    }
}
