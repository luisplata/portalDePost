<?php

namespace Tests\Feature\Ads;

use App\AdCreative;
use App\AdPlacement;
use App\Services\AdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Administracion de placements (REQ-PLC-02, REQ-PLC-04, REQ-ADM-01, REQ-ADM-02).
 *
 * `key` es inmutable (D-01) y cada guardado invalida el slot afectado.
 */
class AdminPlacementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // La data migration siembra creativas legacy desde config_publicities.
        DB::table('ad_creatives')->delete();
    }

    public function test_admin_routes_require_a_session()
    {
        $this->get('/admin/placements')->assertRedirect('/logout');
        $this->put('/admin/placements/home_sidebar', [
            'name' => 'Sin sesion',
            'max_items' => 2,
            'is_active' => 1,
        ])->assertRedirect('/logout');

        $this->assertSame(1, AdPlacement::where('key', 'home_sidebar')->first()->max_items);
    }

    public function test_index_lists_the_four_placements()
    {
        $response = $this->asAdmin()->get('/admin/placements');

        $response->assertStatus(200);

        foreach (['home_sidebar', 'product_sidebar', 'ppv_sidebar', 'product_banner_grid'] as $key) {
            $response->assertSee($key, false);
        }
    }

    public function test_a_valid_update_persists_and_invalidates_the_slot_cache()
    {
        $this->makeCreative('home_sidebar', 'promo-a');
        $this->makeCreative('home_sidebar', 'promo-b');
        $this->assertCount(1, app(AdService::class)->selectForPlacement('home_sidebar'));

        $this->asAdmin()->put('/admin/placements/home_sidebar', [
            'name' => 'Home sidebar editado',
            'max_items' => 2,
            'is_active' => 1,
        ])->assertRedirect('admin/placements');

        $placement = AdPlacement::where('key', 'home_sidebar')->first();
        $this->assertSame('Home sidebar editado', $placement->name);
        $this->assertSame(2, $placement->max_items);
        $this->assertCount(2, app(AdService::class)->selectForPlacement('home_sidebar'));
    }

    public function test_max_items_out_of_range_is_rejected_without_changing_the_row()
    {
        $this->asAdmin()
            ->from('/admin/placements')
            ->put('/admin/placements/home_sidebar', [
                'name' => 'Home sidebar',
                'max_items' => 0,
                'is_active' => 1,
            ])
            ->assertRedirect('/admin/placements')
            ->assertSessionHasErrors('max_items');

        $this->assertSame(1, AdPlacement::where('key', 'home_sidebar')->first()->max_items);
    }

    public function test_key_cannot_be_changed_even_if_sent_in_the_request()
    {
        $this->asAdmin()->put('/admin/placements/home_sidebar', [
            'key' => 'otra_key',
            'name' => 'Home sidebar',
            'max_items' => 3,
            'is_active' => 1,
        ]);

        $this->assertSame(0, AdPlacement::where('key', 'otra_key')->count());
        $this->assertSame(3, AdPlacement::where('key', 'home_sidebar')->first()->max_items);
    }

    public function test_deactivating_a_placement_stops_the_public_slot_from_rendering()
    {
        $this->makeCreative('home_sidebar', 'promo-off');
        $this->get('/')->assertSee('promo-off', false);

        $this->asAdmin()->put('/admin/placements/home_sidebar', [
            'name' => 'Home sidebar',
            'max_items' => 1,
            'is_active' => 0,
        ])->assertRedirect('admin/placements');

        $this->assertFalse(AdPlacement::where('key', 'home_sidebar')->first()->is_active);
        $this->get('/')->assertDontSee('promo-off', false);
    }

    private function asAdmin()
    {
        return $this->withSession(['admin' => (object) ['nombre' => 'tester']]);
    }

    private function makeCreative(string $placementKey, string $campaignKey): AdCreative
    {
        return AdCreative::create([
            'campaign_key' => $campaignKey,
            'name' => $campaignKey,
            'placement_id' => AdPlacement::where('key', $placementKey)->firstOrFail()->id,
            'status' => 'active',
            'priority' => 0,
            'image_url' => 'https://cdn.example/'.$campaignKey.'.jpg',
            'headline' => 'Headline',
            'target_url' => 'https://example.com/'.$campaignKey,
        ]);
    }
}
