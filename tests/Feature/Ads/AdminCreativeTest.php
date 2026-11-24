<?php

namespace Tests\Feature\Ads;

use App\AdCreative;
use App\AdPlacement;
use App\AdStatDaily;
use App\Services\AdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CRUD admin de creatividades (REQ-ADM-01..08, REQ-CRT-01..04).
 *
 * Cubre la excepcion de D-25 (datetime-local local -> UTC), la inmutabilidad
 * del campaign_key (D-01) y el archivo sin borrado (D-03).
 */
class AdminCreativeTest extends TestCase
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
        $this->get('/admin/publicidad/create')->assertRedirect('/logout');
        $this->post('/admin/publicidad', $this->payload())->assertRedirect('/logout');

        $this->assertSame(0, AdCreative::count());
    }

    public function test_create_form_renders_all_the_fields()
    {
        $this->asAdmin()->get('/admin/publicidad/create')
            ->assertStatus(200)
            ->assertSee('name="campaign_key"', false)
            ->assertSee('name="placement_id"', false)
            ->assertSee('name="starts_at"', false)
            ->assertSee('name="producto_id"', false)
            ->assertSee('Home sidebar', false)
            ->assertSee('id="ad-preview-image"', false)
            ->assertSee('id="ad-preview-headline"', false)
            ->assertSee('id="ad-preview-error"', false);
    }

    public function test_store_persists_a_draft_and_accepts_legacy_underscore_keys()
    {
        $response = $this->asAdmin()->post('/admin/publicidad', $this->payload([
            'campaign_key' => 'game-home_sidebar',
            'headline' => 'Legacy sidebar',
            'starts_at' => '2026-12-01T00:00',
        ]));

        $creative = AdCreative::where('campaign_key', 'game-home_sidebar')->firstOrFail();

        $this->assertRedirectsToIndex($response);
        $this->assertSame('draft', $creative->status);
        $this->assertSame('Legacy sidebar', $creative->alt_text);
        $this->assertSame('Ver más', $creative->cta_label);
        $this->assertSame(0, $creative->priority);
        $this->assertSame('home_sidebar', $creative->placement->key);
        // D-25: 2026-12-01T00:00 en Bogota (UTC-5) son las 05:00 UTC.
        $this->assertSame('2026-12-01 05:00:00', $creative->starts_at->toDateTimeString());
    }

    public function test_datetime_local_is_stored_in_utc_and_shown_back_in_local_time()
    {
        $this->asAdmin()->post('/admin/publicidad', $this->payload([
            'campaign_key' => 'promo-window',
            'starts_at' => '2026-11-30T23:30',
            'ends_at' => '2026-12-01T23:30',
        ]))->assertRedirect();

        $creative = AdCreative::where('campaign_key', 'promo-window')->firstOrFail();
        $this->assertSame('2026-12-01 04:30:00', $creative->starts_at->toDateTimeString());
        $this->assertSame('2026-12-02 04:30:00', $creative->ends_at->toDateTimeString());

        $this->asAdmin()->get('admin/publicidad/'.$creative->id.'/edit')
            ->assertStatus(200)
            ->assertSee('value="2026-11-30T23:30"', false)
            ->assertSee('value="2026-12-01T23:30"', false)
            ->assertSee('promo-window', false)
            ->assertSee('disabled', false);
    }

    public function test_invalid_or_duplicate_keys_are_rejected()
    {
        $this->asAdmin()->from('/admin/publicidad/create')
            ->post('/admin/publicidad', $this->payload(['campaign_key' => 'Promo.1']))
            ->assertSessionHasErrors('campaign_key');

        $this->asAdmin()->from('/admin/publicidad/create')
            ->post('/admin/publicidad', $this->payload(['campaign_key' => 'promo 1']))
            ->assertSessionHasErrors('campaign_key');

        $this->assertSame(0, AdCreative::count());

        $this->makeCreative('home_sidebar', 'promo-dup');
        $this->asAdmin()->from('/admin/publicidad/create')
            ->post('/admin/publicidad', $this->payload(['campaign_key' => 'promo-dup']))
            ->assertSessionHasErrors('campaign_key');

        $this->assertSame(1, AdCreative::count());
    }

    public function test_update_cannot_change_the_campaign_key()
    {
        $creative = $this->makeCreative('home_sidebar', 'promo-fixed');

        $this->asAdmin()
            ->from('admin/publicidad/'.$creative->id.'/edit')
            ->put('/admin/publicidad/'.$creative->id, $this->payload([
                'campaign_key' => 'promo-changed',
                'name' => 'Nombre nuevo',
            ]))
            ->assertSessionHasErrors('campaign_key');

        $fresh = $creative->fresh();
        $this->assertSame('promo-fixed', $fresh->campaign_key);
        $this->assertSame($creative->name, $fresh->name);
    }

    public function test_window_with_ends_at_before_starts_at_is_rejected()
    {
        $this->asAdmin()->from('/admin/publicidad/create')
            ->post('/admin/publicidad', $this->payload([
                'starts_at' => '2026-12-02T10:00',
                'ends_at' => '2026-12-01T10:00',
            ]))
            ->assertSessionHasErrors('ends_at');

        $this->assertSame(0, AdCreative::count());
    }

    public function test_unsafe_target_urls_are_rejected()
    {
        foreach (['javascript:alert(1)', '//evil.example/x', 'data:text/html,x'] as $target) {
            $this->asAdmin()->from('/admin/publicidad/create')
                ->post('/admin/publicidad', $this->payload(['target_url' => $target]))
                ->assertSessionHasErrors('target_url');
        }

        $this->assertSame(0, AdCreative::count());
    }

    public function test_status_and_priority_are_validated()
    {
        $this->asAdmin()->from('/admin/publicidad/create')
            ->post('/admin/publicidad', $this->payload(['status' => 'live']))
            ->assertSessionHasErrors('status');

        $this->asAdmin()->from('/admin/publicidad/create')
            ->post('/admin/publicidad', $this->payload(['priority' => 101]))
            ->assertSessionHasErrors('priority');

        $this->assertSame(0, AdCreative::count());
    }

    public function test_moving_a_creative_invalidates_the_old_and_the_new_slot()
    {
        $creative = $this->makeCreative('home_sidebar', 'promo-move');
        $ppv = AdPlacement::where('key', 'ppv_sidebar')->firstOrFail();
        $service = app(AdService::class);

        $this->assertCount(1, $service->selectForPlacement('home_sidebar'));
        $this->assertCount(0, $service->selectForPlacement('ppv_sidebar'));

        $this->asAdmin()->put('/admin/publicidad/'.$creative->id, $this->payload([
            'campaign_key' => 'promo-move',
            'placement_id' => $ppv->id,
            'status' => 'active',
        ]))->assertRedirect();

        $this->assertSame($ppv->id, $creative->fresh()->placement_id);
        $this->assertCount(0, $service->selectForPlacement('home_sidebar'));
        $this->assertCount(1, $service->selectForPlacement('ppv_sidebar'));
    }

    public function test_destroy_archives_and_keeps_the_metrics()
    {
        $creative = $this->makeCreative('home_sidebar', 'promo-end', ['status' => 'active']);
        AdStatDaily::create([
            'creative_id' => $creative->id,
            'placement_id' => $creative->placement_id,
            'date' => '2026-11-24',
            'impressions' => 10,
            'clicks' => 2,
        ]);
        $this->assertCount(1, app(AdService::class)->selectForPlacement('home_sidebar'));

        $response = $this->asAdmin()->delete('/admin/publicidad/'.$creative->id);

        $this->assertRedirectsToIndex($response);
        $fresh = $creative->fresh();
        $this->assertSame('ended', $fresh->status);
        $this->assertSame(10, AdStatDaily::where('creative_id', $creative->id)->first()->impressions);
        $this->assertCount(0, app(AdService::class)->selectForPlacement('home_sidebar'));
    }

    public function test_a_post_without_csrf_token_is_rejected()
    {
        // Los tests saltan CSRF; se apaga el bypass para ejercitar el middleware real.
        $this->app['env'] = 'production';

        $this->asAdmin()->post('/admin/publicidad', $this->payload())->assertStatus(419);

        $this->assertSame(0, AdCreative::count());
    }

    private function asAdmin()
    {
        return $this->withSession(['admin' => (object) ['nombre' => 'tester']]);
    }

    private function assertRedirectsToIndex($response)
    {
        $response->assertRedirect();
        $this->assertStringStartsWith(
            url('admin/publicidad?mensaje='),
            (string) $response->headers->get('Location')
        );
    }

    private function payload(array $overrides = [])
    {
        return array_merge([
            'campaign_key' => 'promo-s2b',
            'name' => 'Promo S2B',
            'placement_id' => AdPlacement::where('key', 'home_sidebar')->value('id'),
            'status' => 'draft',
            'priority' => 0,
            'image_url' => 'https://cdn.example/promo.jpg',
            'headline' => 'Promo headline',
            'target_url' => 'https://example.com/promo',
        ], $overrides);
    }

    private function makeCreative(string $placementKey, string $campaignKey, array $overrides = []): AdCreative
    {
        return AdCreative::create(array_merge([
            'campaign_key' => $campaignKey,
            'name' => $campaignKey,
            'placement_id' => AdPlacement::where('key', $placementKey)->firstOrFail()->id,
            'status' => 'active',
            'priority' => 0,
            'image_url' => 'https://cdn.example/'.$campaignKey.'.jpg',
            'headline' => 'Headline',
            'target_url' => 'https://example.com/'.$campaignKey,
        ], $overrides));
    }
}
