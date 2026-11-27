<?php

namespace Tests\Unit\Ads;

use App\AdClick;
use App\AdCreative;
use App\AdPlacement;
use App\AdStatDaily;
use App\Services\AdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contrato de REQ-SEL-01, REQ-SEL-02, REQ-SEL-06 y REQ-PLC-03.
 *
 * La seleccion es deterministica (priority desc, id asc) y se cachea por
 * slot en `ads:slot:{key}`: 2 queries en miss, 0 en hit.
 */
class AdSelectionTest extends TestCase
{
    use RefreshDatabase;

    /** @var \App\Services\AdService */
    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        // La data migration siembra 3 creatividades legacy; cada test parte limpio.
        DB::table('ad_creatives')->delete();
        Cache::flush();

        $this->service = app(AdService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_orders_by_priority_descending_then_id_ascending_and_cuts_to_max_items()
    {
        $placement = $this->makePlacement('home_sidebar', 2);
        $low = $this->makeCreative($placement, ['priority' => 0]);
        $high = $this->makeCreative($placement, ['priority' => 5]);
        $mid = $this->makeCreative($placement, ['priority' => 1]);

        $result = $this->service->selectForPlacement('home_sidebar');

        $this->assertSame(
            [$high->campaign_key, $mid->campaign_key],
            array_column($result, 'campaign_key')
        );
        $this->assertNotContains($low->campaign_key, array_column($result, 'campaign_key'));
    }

    public function test_priority_ties_are_broken_by_the_lower_id_and_stay_deterministic()
    {
        $placement = $this->makePlacement('home_sidebar', 1);
        $first = $this->makeCreative($placement, ['priority' => 7]);
        $this->makeCreative($placement, ['priority' => 7]);

        $initial = $this->service->selectForPlacement('home_sidebar');
        $again = $this->service->selectForPlacement('home_sidebar');

        $this->assertSame([$first->campaign_key], array_column($initial, 'campaign_key'));
        $this->assertSame($initial, $again);
    }

    public function test_cuts_the_selection_to_the_placement_max_items()
    {
        $placement = $this->makePlacement('product_banner_grid', 1);

        for ($i = 0; $i < 6; $i++) {
            $this->makeCreative($placement, ['priority' => $i]);
        }

        $this->assertCount(1, $this->service->selectForPlacement('product_banner_grid'));
    }

    public function test_excludes_creatives_outside_their_temporal_window()
    {
        Carbon::setTestNow('2026-05-10 12:00:00');
        $placement = $this->makePlacement('home_sidebar', 10);

        $inside = $this->makeCreative($placement, [
            'starts_at' => '2026-05-01 00:00:00',
            'ends_at' => '2026-05-20 00:00:00',
        ]);
        $open = $this->makeCreative($placement, ['starts_at' => null, 'ends_at' => null]);
        $this->makeCreative($placement, ['starts_at' => '2026-05-11 00:00:00']);
        $this->makeCreative($placement, ['ends_at' => '2026-05-09 00:00:00']);

        $keys = array_column($this->service->selectForPlacement('home_sidebar'), 'campaign_key');

        $this->assertEqualsCanonicalizing([$inside->campaign_key, $open->campaign_key], $keys);
        $this->assertCount(2, $keys);
    }

    public function test_treats_the_window_boundaries_as_inclusive()
    {
        Carbon::setTestNow('2026-05-10 12:00:00');
        $placement = $this->makePlacement('home_sidebar', 10);
        $creative = $this->makeCreative($placement, [
            'starts_at' => '2026-05-10 12:00:00',
            'ends_at' => '2026-05-10 12:00:00',
        ]);

        $this->assertSame(
            [$creative->campaign_key],
            array_column($this->service->selectForPlacement('home_sidebar'), 'campaign_key')
        );
    }

    public function test_serves_only_active_status()
    {
        $placement = $this->makePlacement('home_sidebar', 10);
        $active = $this->makeCreative($placement, ['status' => 'active']);
        $this->makeCreative($placement, ['status' => 'draft']);
        $this->makeCreative($placement, ['status' => 'paused']);
        $this->makeCreative($placement, ['status' => 'ended']);

        $this->assertSame(
            [$active->campaign_key],
            array_column($this->service->selectForPlacement('home_sidebar'), 'campaign_key')
        );
    }

    public function test_returns_empty_for_an_unknown_placement()
    {
        $placement = $this->makePlacement('home_sidebar', 10);
        $this->makeCreative($placement);

        $this->assertSame([], $this->service->selectForPlacement('does_not_exist'));
    }

    public function test_returns_empty_for_an_inactive_placement()
    {
        $placement = $this->makePlacement('home_sidebar', 10, false);
        $this->makeCreative($placement);

        $this->assertSame([], $this->service->selectForPlacement('home_sidebar'));
    }

    public function test_a_cache_miss_runs_two_queries_and_a_hit_runs_none()
    {
        $placement = $this->makePlacement('home_sidebar', 10);
        $this->makeCreative($placement);
        Cache::flush();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->service->selectForPlacement('home_sidebar');
        $miss = $queries;

        $this->service->selectForPlacement('home_sidebar');

        $this->assertSame(2, $miss, 'Un miss debe resolver placement + creatividades.');
        $this->assertSame(2, $queries, 'Un hit no debe ejecutar consultas.');
    }

    public function test_forget_slot_forces_a_fresh_selection()
    {
        $placement = $this->makePlacement('home_sidebar', 1);
        $cached = $this->makeCreative($placement, ['priority' => 0]);

        $this->assertSame(
            [$cached->campaign_key],
            array_column($this->service->selectForPlacement('home_sidebar'), 'campaign_key')
        );

        $fresh = $this->makeCreative($placement, ['priority' => 9]);

        $this->assertSame(
            [$cached->campaign_key],
            array_column($this->service->selectForPlacement('home_sidebar'), 'campaign_key')
        );

        $this->service->forgetSlot('home_sidebar');

        $this->assertSame(
            [$fresh->campaign_key],
            array_column($this->service->selectForPlacement('home_sidebar'), 'campaign_key')
        );
    }

    public function test_the_cached_payload_exposes_only_the_public_fields()
    {
        $placement = $this->makePlacement('home_sidebar', 1);
        $this->makeCreative($placement);

        $fields = array_keys($this->service->selectForPlacement('home_sidebar')[0]);
        sort($fields);

        $this->assertSame([
            'alt_text',
            'body',
            'campaign_key',
            'cta_label',
            'headline',
            'image_url',
            'name',
            'target_url',
        ], $fields);
    }

    public function test_present_public_adds_an_absolute_click_url()
    {
        $rows = [[
            'campaign_key' => 'promo-x',
            'name' => 'Promo',
            'image_url' => 'https://cdn.example/promo.jpg',
            'alt_text' => 'Promo alt',
            'headline' => 'Promo headline',
            'body' => null,
            'cta_label' => 'Ver más',
            'target_url' => 'https://example.com/promo',
        ]];

        $presented = $this->service->presentPublic($rows);

        $this->assertSame(url('/ads/promo-x'), $presented[0]['click_url']);
        $this->assertMatchesRegularExpression('#^https?://#', $presented[0]['click_url']);
    }

    public function test_models_use_the_contract_table_names()
    {
        $this->assertSame('ad_placements', (new AdPlacement())->getTable());
        $this->assertSame('ad_creatives', (new AdCreative())->getTable());
        $this->assertSame('ad_stats_daily', (new AdStatDaily())->getTable());
        $this->assertSame('ad_clicks', (new AdClick())->getTable());
        $this->assertNull(AdClick::UPDATED_AT);
    }

    private function makePlacement(string $key, int $maxItems = 1, bool $active = true): AdPlacement
    {
        // Los 4 placements ya existen por la migracion: se editan, no se crean.
        $placement = AdPlacement::firstOrNew(['key' => $key]);
        $placement->name = ucfirst(str_replace('_', ' ', $key));
        $placement->max_items = $maxItems;
        $placement->is_active = $active;
        $placement->save();

        return $placement;
    }

    private function makeCreative(AdPlacement $placement, array $attributes = []): AdCreative
    {
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
}
