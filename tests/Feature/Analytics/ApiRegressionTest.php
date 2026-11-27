<?php

namespace Tests\Feature\Analytics;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Caracterizacion de los endpoints publicos que no son el ranking
 * (REG-04, REG-05).
 *
 * Congela las claves que hoy devuelve cada endpoint para detectar cualquier
 * cambio de shape cuando se corrija el ranking.
 */
class ApiRegressionTest extends AnalyticsTestSupport
{
    use RefreshDatabase;

    /**
     * Claves de un producto serializado sin contadores de visitas.
     */
    private const PRODUCT_KEYS = [
        'NombreLink',
        'hotLink',
        'id',
        'imagen',
        'isVideo',
        'nombre',
        'published',
        'tags',
        'url_video',
    ];

    /**
     * `/api/model/{id}` agrega la relacion `visitas` al cargar los contadores.
     */
    private const MODEL_KEYS = [
        'NombreLink',
        'hotLink',
        'id',
        'imagen',
        'isVideo',
        'nombre',
        'published',
        'tags',
        'url_video',
        'visitas',
    ];

    public function test_home_keeps_its_top_level_keys()
    {
        $this->makeProduct();

        $response = $this->getJson('/api/home')->assertStatus(200);

        $this->assertSame(
            ['banners', 'hot', 'other', 'packs', 'popular'],
            $this->keysOf($response->json())
        );
    }

    public function test_banners_keep_the_product_shape()
    {
        $this->makeProduct();

        $response = $this->getJson('/api/banners')->assertStatus(200);

        $items = $response->json();

        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        $this->assertSame(self::PRODUCT_KEYS, $this->keysOf($items[0]));
    }

    public function test_packs_keep_the_paginator_shape()
    {
        $this->makeProduct();

        $response = $this->getJson('/api/packs')->assertStatus(200);

        $payload = $response->json();

        $this->assertSame([
            'current_page',
            'data',
            'first_page_url',
            'from',
            'last_page',
            'last_page_url',
            'links',
            'next_page_url',
            'path',
            'per_page',
            'prev_page_url',
            'to',
            'total',
        ], $this->keysOf($payload));
        $this->assertSame(self::PRODUCT_KEYS, $this->keysOf($payload['data'][0]));
    }

    public function test_tags_keep_their_list_shape()
    {
        $this->makeProduct(['tags' => 'alpha']);

        $response = $this->getJson('/api/tags')->assertStatus(200);

        $this->assertSame(['alpha'], $response->json());
    }

    public function test_model_show_keeps_the_product_shape_and_counts_the_visit()
    {
        $productoId = $this->makeProduct();

        $response = $this->getJson('/api/model/'.$productoId)->assertStatus(200);

        $this->assertSame(self::MODEL_KEYS, $this->keysOf($response->json()));
        $this->assertSame(1, (int) \DB::table('visit_posts')
            ->where('producto_id', $productoId)
            ->value('visita'));
    }

    public function test_model_redirect_by_id_keeps_redirect_to_and_counts_the_click()
    {
        $productoId = $this->makeProduct(['hotLink' => 'https://example.com/model-id']);

        $response = $this->getJson('/api/model/redirect/id/'.$productoId)->assertStatus(200);

        $this->assertSame(['redirect_to' => 'https://example.com/model-id'], $response->json());
        $this->assertSame(1, (int) \DB::table('visit_posts')
            ->where('producto_id', $productoId)
            ->value('idoAlPack'));
    }

    public function test_model_redirect_by_name_keeps_redirect_to_and_counts_the_click()
    {
        $productoId = $this->makeProduct([
            'nombre' => 'ModelName',
            'hotLink' => 'https://example.com/model-name',
        ]);

        $response = $this->getJson('/api/model/redirect/name/ModelName')->assertStatus(200);

        $this->assertSame(['redirect_to' => 'https://example.com/model-name'], $response->json());
        $this->assertSame(1, (int) \DB::table('visit_posts')
            ->where('producto_id', $productoId)
            ->value('idoAlPack'));
    }
}
