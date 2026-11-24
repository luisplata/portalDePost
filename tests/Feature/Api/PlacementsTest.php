<?php

namespace Tests\Feature\Api;

use App\AdCreative;
use App\AdPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contrato de REQ-API-01, REQ-API-02 y REQ-API-03.
 *
 * /api/placements/{key} expone solo campos publicos; /api/home y /api/banners
 * conservan el baseline de claves capturado antes del cambio.
 */
class PlacementsTest extends TestCase
{
    use RefreshDatabase;

    /** Baseline de /api/home capturado antes de implementar S1a-2. */
    const HOME_BASELINE_KEYS = ['banners', 'hot', 'popular', 'packs', 'other'];

    /** Baseline de cada item de /api/banners capturado antes de implementar S1a-2. */
    const BANNERS_BASELINE_ITEM_KEYS = [
        'id',
        'nombre',
        'imagen',
        'NombreLink',
        'hotLink',
        'isVideo',
        'url_video',
        'tags',
        'published',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('ad_creatives')->delete();
        Cache::flush();
    }

    public function test_returns_the_exact_public_shape()
    {
        $this->makeCreative('home_sidebar', ['campaign_key' => 'promo-api']);

        $response = $this->getJson('/api/placements/home_sidebar');

        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(['placement', 'creatives'], array_keys($json));
        $this->assertSame(['key', 'name', 'max_items'], array_keys($json['placement']));
        $this->assertSame('home_sidebar', $json['placement']['key']);
        $this->assertSame(1, $json['placement']['max_items']);
        $this->assertCount(1, $json['creatives']);
        $this->assertSame([
            'campaign_key',
            'name',
            'image_url',
            'alt_text',
            'headline',
            'body',
            'cta_label',
            'target_url',
            'click_url',
        ], array_keys($json['creatives'][0]));
    }

    public function test_click_url_is_absolute_and_built_for_the_current_host()
    {
        $this->makeCreative('home_sidebar', ['campaign_key' => 'promo-click']);

        $json = $this->getJson('/api/placements/home_sidebar')->json();

        $this->assertSame(url('/ads/promo-click'), $json['creatives'][0]['click_url']);
        $this->assertMatchesRegularExpression('#^https?://#', $json['creatives'][0]['click_url']);
    }

    public function test_does_not_leak_internal_fields()
    {
        $this->makeCreative('home_sidebar', [
            'campaign_key' => 'promo-internal',
            'priority' => 42,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $creative = $this->getJson('/api/placements/home_sidebar')->json('creatives.0');

        foreach ([
            'id',
            'status',
            'priority',
            'producto_id',
            'starts_at',
            'ends_at',
            'created_at',
            'updated_at',
            'placement_id',
        ] as $internal) {
            $this->assertArrayNotHasKey($internal, $creative);
        }
    }

    public function test_returns_404_with_the_contract_error_for_an_unknown_placement()
    {
        $response = $this->getJson('/api/placements/nope');

        $response->assertStatus(404);
        $this->assertSame(['error' => 'placement_not_found'], $response->json());
    }

    public function test_returns_200_with_empty_creatives_for_an_inactive_placement()
    {
        $placement = AdPlacement::where('key', 'home_sidebar')->firstOrFail();
        $placement->is_active = false;
        $placement->save();

        $this->makeCreative('home_sidebar', ['campaign_key' => 'promo-off']);

        $response = $this->getJson('/api/placements/home_sidebar');

        $response->assertStatus(200);
        $this->assertSame([], $response->json('creatives'));
        $this->assertSame('home_sidebar', $response->json('placement.key'));
    }

    public function test_works_without_authentication()
    {
        $this->makeCreative('product_sidebar', ['campaign_key' => 'promo-anon']);

        $response = $this->getJson('/api/placements/product_sidebar');

        $response->assertStatus(200);
        $response->assertJsonPath('creatives.0.campaign_key', 'promo-anon');
    }

    public function test_home_api_keeps_its_baseline_first_level_keys()
    {
        $response = $this->getJson('/api/home');

        $response->assertStatus(200);
        $this->assertSame(self::HOME_BASELINE_KEYS, array_keys($response->json()));
    }

    public function test_banners_api_keeps_the_product_fields_baseline()
    {
        $this->makeProduct('Producto Banner', 'banner-tag');

        $response = $this->getJson('/api/banners');

        $response->assertStatus(200);

        $banners = $response->json();

        $this->assertIsArray($banners);
        $this->assertCount(1, $banners);
        $this->assertSame(self::BANNERS_BASELINE_ITEM_KEYS, array_keys($banners[0]));
        $this->assertSame('Producto Banner', $banners[0]['nombre']);
    }

    private function makeCreative(string $placementKey, array $attributes = []): AdCreative
    {
        $placement = AdPlacement::where('key', $placementKey)->firstOrFail();

        return AdCreative::create(array_merge([
            'campaign_key' => 'test-'.uniqid(),
            'name' => 'Creatividad de prueba',
            'placement_id' => $placement->id,
            'status' => 'active',
            'starts_at' => null,
            'ends_at' => null,
            'priority' => 0,
            'image_url' => 'https://cdn.example/creative.jpg',
            'alt_text' => 'Alt de prueba',
            'headline' => 'Headline de prueba',
            'body' => null,
            'cta_label' => 'Ver más',
            'target_url' => 'https://example.com/target',
            'producto_id' => null,
        ], $attributes));
    }

    private function makeProduct(string $nombre, string $tags): int
    {
        $categoriaId = DB::table('categorias')->insertGetId([
            'nombre' => 'Categoria '.$tags,
            'descripcion' => 'Categoria de prueba',
            'padre' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('productos')->insertGetId([
            'nombre' => $nombre,
            'imagen' => 'https://cdn.example/product.jpg',
            'categorias_id' => $categoriaId,
            'estado' => 1,
            'NombreLink' => 'PCK 1',
            'hotLink' => 'https://example.com/hot',
            'publication_date' => now()->subDay(),
            'isVideo' => '0',
            'url_video' => '',
            'tags' => $tags,
            'published' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
