<?php

namespace Tests\Feature\Analytics;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Caracterizacion del contrato publico del ranking (REQ-S1-05, REG-03).
 *
 * Estos tests congelan el shape actual de `/api/hot` y `/api/popular` antes
 * de tocar el codigo: 200, array de hasta 3 items y exactamente 12 claves.
 * Se comparan conjuntos de claves, nunca valores, para no congelar datos.
 */
class PublicRankingContractTest extends AnalyticsTestSupport
{
    use RefreshDatabase;

    /**
     * Las 12 claves contractuales de cada item del ranking.
     */
    private const RANKING_KEYS = [
        'NombreLink',
        'hotLink',
        'id',
        'idoAlPack',
        'imagen',
        'isVideo',
        'nombre',
        'producto_id',
        'published',
        'tags',
        'url_video',
        'visita',
    ];

    public function test_hot_responds_200_with_at_most_three_items_and_the_twelve_keys()
    {
        $productoId = $this->makeProduct(['nombre' => 'Hot Contrato']);
        $this->makeVisitPost($productoId, 3, 1);

        $response = $this->getJson('/api/hot')->assertStatus(200);

        $items = $response->json();

        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        $this->assertLessThanOrEqual(3, count($items));
        $this->assertSame(self::RANKING_KEYS, $this->keysOf($items[0]));
    }

    public function test_popular_responds_200_with_at_most_three_items_and_the_twelve_keys()
    {
        $productoId = $this->makeProduct(['nombre' => 'Popular Contrato']);
        $this->makeVisitPost($productoId, 3, 1);

        $response = $this->getJson('/api/popular')->assertStatus(200);

        $items = $response->json();

        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        $this->assertLessThanOrEqual(3, count($items));
        $this->assertSame(self::RANKING_KEYS, $this->keysOf($items[0]));
    }

    public function test_popular_returns_an_empty_array_when_nothing_can_be_ranked()
    {
        $response = $this->getJson('/api/popular')->assertStatus(200);

        $this->assertSame([], $response->json());
    }
}
