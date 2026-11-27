<?php

namespace Tests\Feature\Analytics;

use App\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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

    public function test_ranking_ids_are_valid_product_ids()
    {
        // El producto descartable ocupa el id 1 para que el id de `visit_posts`
        // (tambien 1) no coincida por accidente con el `productos.id` objetivo.
        $this->makeProduct(['nombre' => 'Descartable', 'estado' => 0]);
        $targetId = $this->makeProduct(['nombre' => 'Objetivo']);
        $this->makeVisitPost($targetId, 3, 1);

        foreach (['/api/hot', '/api/popular'] as $uri) {
            $items = $this->getJson($uri)->assertStatus(200)->json();

            $this->assertNotEmpty($items);

            foreach ($items as $item) {
                $this->assertSame($item['id'], $item['producto_id']);
                $this->assertIsInt($item['id']);
                $this->assertIsInt($item['producto_id']);
                $this->assertNotNull(Producto::find($item['id']));
            }
        }
    }

    public function test_inactive_products_are_excluded_from_the_ranking()
    {
        $activoId = $this->makeProduct(['nombre' => 'Activo']);
        $this->makeVisitPost($activoId, 1, 0);

        $inactivoId = $this->makeProduct(['nombre' => 'Inactivo', 'estado' => 0]);
        $this->makeVisitPost($inactivoId, 100, 10);

        foreach (['/api/hot', '/api/popular'] as $uri) {
            $ids = array_column($this->getJson($uri)->assertStatus(200)->json(), 'producto_id');

            $this->assertContains($activoId, $ids);
            $this->assertNotContains($inactivoId, $ids);
        }
    }

    public function test_published_flag_does_not_filter_the_ranking()
    {
        $productoId = $this->makeProduct(['nombre' => 'No Publicado', 'published' => 0]);
        $this->makeVisitPost($productoId, 2, 0);

        $ids = array_column($this->getJson('/api/hot')->assertStatus(200)->json(), 'producto_id');

        $this->assertContains($productoId, $ids);
    }

    public function test_products_without_visit_rows_rank_with_zero_counters()
    {
        foreach (range(1, 2) as $i) {
            $productoId = $this->makeProduct(['nombre' => 'Con Contadores '.$i]);
            $this->makeVisitPost($productoId, 5 - $i, 0);
        }

        foreach (range(1, 12) as $i) {
            $this->makeProduct(['nombre' => 'Sin Contadores '.$i]);
        }

        $items = $this->getJson('/api/popular')->assertStatus(200)->json();

        $this->assertCount(3, $items);

        $zeroItems = array_values(array_filter($items, function (array $item) {
            return $item['visita'] === 0 && $item['idoAlPack'] === 0;
        }));

        $this->assertNotEmpty($zeroItems);

        foreach ($zeroItems as $item) {
            $this->assertIsInt($item['visita']);
            $this->assertIsInt($item['idoAlPack']);
        }
    }

    public function test_popular_orders_by_visits_then_clicks_then_id()
    {
        $a = $this->makeProduct(['nombre' => 'Popular A']);
        $this->makeVisitPost($a, 5, 1);

        $b = $this->makeProduct(['nombre' => 'Popular B']);
        $this->makeVisitPost($b, 5, 3);

        $c = $this->makeProduct(['nombre' => 'Popular C']);
        $this->makeVisitPost($c, 0, 4);

        $ids = array_column($this->getJson('/api/popular')->assertStatus(200)->json(), 'producto_id');

        $this->assertSame([$b, $a, $c], $ids);
    }

    public function test_hot_orders_by_ratio_then_visits_then_id()
    {
        $a = $this->makeProduct(['nombre' => 'Hot A']);
        $this->makeVisitPost($a, 10, 5);

        $b = $this->makeProduct(['nombre' => 'Hot B']);
        $this->makeVisitPost($b, 20, 10);

        $c = $this->makeProduct(['nombre' => 'Hot C']);
        $this->makeVisitPost($c, 10, 2);

        $ids = array_column($this->getJson('/api/hot')->assertStatus(200)->json(), 'producto_id');

        $this->assertSame([$b, $a, $c], $ids);
    }

    public function test_hot_treats_zero_visits_as_a_zero_ratio()
    {
        $sinVisitas = $this->makeProduct(['nombre' => 'Sin Visitas']);
        $this->makeVisitPost($sinVisitas, 0, 4);

        $conRatio = $this->makeProduct(['nombre' => 'Con Ratio']);
        $this->makeVisitPost($conRatio, 10, 1);

        $ids = array_column($this->getJson('/api/hot')->assertStatus(200)->json(), 'producto_id');

        $this->assertSame([$conRatio, $sinVisitas], $ids);
    }

    public function test_duplicate_visit_rows_are_aggregated_into_a_single_item()
    {
        $productoId = $this->makeProduct(['nombre' => 'Duplicado']);
        $this->makeVisitPost($productoId, 2, 0);
        $this->makeVisitPost($productoId, 3, 0);

        $items = $this->getJson('/api/popular')->assertStatus(200)->json();

        $this->assertCount(1, $items);
        $this->assertSame($productoId, $items[0]['producto_id']);
        $this->assertSame(5, $items[0]['visita']);
        $this->assertSame(0, $items[0]['idoAlPack']);
    }

    public function test_ranking_query_count_is_constant_and_small()
    {
        $this->makeProduct();

        $baseline = $this->countHotQueries();

        foreach (range(1, 47) as $i) {
            $this->makeProduct(['nombre' => 'Producto Extra '.$i]);
        }

        $withMany = $this->countHotQueries();

        $this->assertLessThanOrEqual(3, $baseline);
        $this->assertSame($baseline, $withMany);
    }

    private function countHotQueries(): int
    {
        $count = 0;

        DB::listen(function () use (&$count) {
            $count++;
        });

        $this->getJson('/api/hot')->assertStatus(200);

        return $count;
    }
}
