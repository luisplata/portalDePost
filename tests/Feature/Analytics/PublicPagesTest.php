<?php

namespace Tests\Feature\Analytics;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Caracterizacion de las paginas publicas que consumen el ranking
 * (REQ-S1-07, REG-01).
 *
 * La home y la ficha deben seguir respondiendo 200 y renderizando las
 * secciones "Hot Post" y "Popular Post" con enlaces a `/content/{slug}`.
 */
class PublicPagesTest extends AnalyticsTestSupport
{
    use RefreshDatabase;

    public function test_home_renders_hot_and_popular_sections_with_content_links()
    {
        $productoId = $this->makeProduct(['nombre' => 'Hot Producto']);
        $this->makeVisitPost($productoId, 5, 2);

        $response = $this->get('/')->assertStatus(200);

        $response->assertSee('Hot Post');
        $response->assertSee('Popular Post');
        $response->assertSee('/content/Hot-Producto', false);
    }

    public function test_content_page_renders_hot_and_popular_sections()
    {
        $productoId = $this->makeProduct(['nombre' => 'Hot Producto']);
        $this->makeVisitPost($productoId, 5, 2);

        $response = $this->get('/content/Hot-Producto')->assertStatus(200);

        $response->assertSee('Hot Post');
        $response->assertSee('Popular Post');
        $response->assertSee('/content/Hot-Producto', false);
    }
}
