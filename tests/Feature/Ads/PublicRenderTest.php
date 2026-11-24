<?php

namespace Tests\Feature\Ads;

use App\AdCreative;
use App\AdPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contrato de REQ-SEL-03, REQ-SEL-04, REQ-SEL-05, REQ-SEL-06, REQ-PLC-03
 * y REQ-CMP-02 para el render publico.
 */
class PublicRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('ad_creatives')->delete();
        Cache::flush();
    }

    public function test_home_renders_the_active_creative_with_tracking_markup()
    {
        $creative = $this->makeCreative('home_sidebar', [
            'campaign_key' => 'promo-home',
            'image_url' => 'https://cdn.example/home.jpg',
            'alt_text' => 'Alternativa home',
            'headline' => 'Headline home',
        ]);

        $response = $this->withCookie('anonymous_user_id', 'user-uuid-123')
            ->get('/');

        $response->assertStatus(200);
        $response->assertSee('/ads/'.$creative->campaign_key, false);
        $response->assertSee('src="https://cdn.example/home.jpg"', false);
        $response->assertSee('alt="Alternativa home"', false);
        $response->assertSee('data-campaign-key="promo-home"', false);
        $response->assertSee('data-placement="home_sidebar"', false);
        $response->assertSee('data-uuid="user-uuid-123"', false);
        $response->assertDontSee('publicity/game', false);
    }

    public function test_home_renders_nothing_when_the_slot_is_empty()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertDontSee('Publicity');
        $response->assertDontSee('data-ad-slot', false);
        $response->assertDontSee('data-placement="home_sidebar"', false);
    }

    public function test_an_inactive_placement_renders_nothing_and_the_page_still_responds_200()
    {
        $placement = AdPlacement::where('key', 'home_sidebar')->firstOrFail();
        $placement->is_active = false;
        $placement->save();

        $this->makeCreative('home_sidebar', ['campaign_key' => 'promo-hidden']);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertDontSee('promo-hidden', false);
        $response->assertDontSee('data-placement="home_sidebar"', false);
    }

    public function test_product_page_grid_renders_banner_creatives_instead_of_recent_products()
    {
        $this->makeProduct('Visto Producto', 'visto-tag');
        $this->makeProduct('Banner Producto Reciente', 'banner-tag');

        $creative = $this->makeCreative('product_banner_grid', [
            'campaign_key' => 'promo-grid',
            'image_url' => 'https://cdn.example/grid.jpg',
            'alt_text' => 'Alternativa grilla',
            'headline' => 'Headline grilla',
        ]);

        $response = $this->get('/content/Visto-Producto');

        $response->assertStatus(200);
        $response->assertSee('id="banner"', false);
        $response->assertSee('src="https://cdn.example/grid.jpg"', false);
        $response->assertSee('data-campaign-key="promo-grid"', false);
        $response->assertSee('data-placement="product_banner_grid"', false);
        $response->assertDontSee('Banner Producto Reciente', false);
    }

    public function test_product_page_renders_nothing_in_the_grid_when_the_slot_is_empty()
    {
        $this->makeProduct('Visto Producto', 'visto-tag');

        $response = $this->get('/content/Visto-Producto');

        $response->assertStatus(200);
        $response->assertDontSee('id="banner"', false);
    }

    public function test_product_page_sidebar_renders_the_product_placement_creative()
    {
        $this->makeProduct('Visto Producto', 'visto-tag');

        $this->makeCreative('product_sidebar', [
            'campaign_key' => 'promo-side',
            'image_url' => 'https://cdn.example/side.jpg',
        ]);

        $response = $this->get('/content/Visto-Producto');

        $response->assertStatus(200);
        $response->assertSee('/ads/promo-side', false);
        $response->assertSee('data-placement="product_sidebar"', false);
        $response->assertDontSee('publicity/game', false);
    }

    public function test_the_slot_partial_never_reads_env()
    {
        $template = file_get_contents(resource_path('views/ads/slot.blade.php'));

        $this->assertStringNotContainsString('env(', $template);
        $this->assertStringNotContainsString('env (', $template);
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
