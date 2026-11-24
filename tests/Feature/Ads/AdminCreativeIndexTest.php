<?php

namespace Tests\Feature\Ads;

use App\AdCreative;
use App\AdPlacement;
use App\AdStatDaily;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Listado admin de creatividades con metricas de 30 dias (REQ-ADM-06).
 *
 * La ventana es local (config('ads.timezone')) y se resuelve en PHP (E-1):
 * los tests fijan `Carbon::setTestNow()` en UTC y verifican el corte local.
 */
class AdminCreativeIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // La data migration siembra creativas legacy desde config_publicities.
        DB::table('ad_creatives')->delete();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_index_requires_a_session()
    {
        $this->get('/admin/publicidad')->assertRedirect('/logout');
    }

    public function test_index_lists_creatives_menu_and_actions()
    {
        $this->makeCreative('ppv_sidebar', 'promo-ppv', ['name' => 'Promo PPV']);
        $this->makeCreative('home_sidebar', 'promo-list', ['name' => 'Promo Listado']);

        $this->asAdmin()->get('/admin/publicidad')
            ->assertStatus(200)
            ->assertSee('promo-list')
            ->assertSee('Promo Listado')
            ->assertSeeInOrder(['Home sidebar', 'PPV sidebar'])
            ->assertSee(url('admin/publicidad/create'), false)
            ->assertSee(url('admin/placements'), false)
            // Item de menu (REQ-ADM-03) junto a Graficas.
            ->assertSee('href="'.url('admin/publicidad').'"', false);
    }

    public function test_thirty_day_metrics_are_local_and_ignore_older_rows()
    {
        // 02:00 UTC del 25 = 21:00 local del 24: el dia operativo es el 24.
        Carbon::setTestNow('2026-11-25 02:00:00');

        $creative = $this->makeCreative('home_sidebar', 'promo-metrics');
        $old = $this->makeCreative('home_sidebar', 'promo-old');
        $this->seedStats($creative, '2026-11-24', 60, 3);
        $this->seedStats($creative, '2026-11-01', 40, 2);
        $this->seedStats($creative, '2026-11-25', 7, 7); // ya es "manana" local
        $this->seedStats($old, '2026-10-01', 500, 5);    // fuera de los 30 dias

        $response = $this->asAdmin()->get('/admin/publicidad')->assertStatus(200);

        $response->assertViewHas('metrics', function (array $metrics) use ($creative, $old) {
            return $metrics[$creative->id]['impressions'] === 100
                && $metrics[$creative->id]['clicks'] === 5
                && $metrics[$creative->id]['ctr'] === 5.0
                && ! isset($metrics[$old->id]);
        });

        $response->assertSee('5.00%')
            ->assertSee('promo-old');
    }

    public function test_ctr_is_zero_without_impressions()
    {
        Carbon::setTestNow('2026-11-25 02:00:00');
        $creative = $this->makeCreative('home_sidebar', 'promo-zero');
        $this->seedStats($creative, '2026-11-24', 0, 3);

        $response = $this->asAdmin()->get('/admin/publicidad')->assertStatus(200);

        $response->assertViewHas('metrics', function (array $metrics) use ($creative) {
            return $metrics[$creative->id]['ctr'] === 0.0
                && $metrics[$creative->id]['clicks'] === 3;
        });
        $response->assertSee('0.00%');
    }

    public function test_metrics_use_a_single_aggregate_query()
    {
        Carbon::setTestNow('2026-11-25 02:00:00');

        for ($i = 1; $i <= 3; $i++) {
            $creative = $this->makeCreative('home_sidebar', 'promo-n1-'.$i);
            $this->seedStats($creative, '2026-11-20', 10, 1);
        }

        $statsQueries = 0;
        DB::listen(function ($query) use (&$statsQueries) {
            if (strpos($query->sql, 'ad_stats_daily') !== false) {
                $statsQueries++;
            }
        });

        $this->asAdmin()->get('/admin/publicidad')->assertStatus(200);

        $this->assertSame(1, $statsQueries);
    }

    public function test_index_paginates_at_twenty()
    {
        for ($i = 1; $i <= 21; $i++) {
            $this->makeCreative('home_sidebar', 'promo-'.str_pad($i, 2, '0', STR_PAD_LEFT));
        }

        $this->asAdmin()->get('/admin/publicidad')
            ->assertStatus(200)
            ->assertViewHas('creatives', function ($paginator) {
                return $paginator->count() === 20 && $paginator->total() === 21;
            });

        $this->asAdmin()->get('/admin/publicidad?page=2')
            ->assertStatus(200)
            ->assertViewHas('creatives', function ($paginator) {
                return $paginator->count() === 1 && $paginator->currentPage() === 2;
            });
    }

    public function test_store_update_and_destroy_redirect_to_the_index()
    {
        $response = $this->asAdmin()->post('/admin/publicidad', $this->payload());
        $this->assertRedirectsToIndex($response);
        $creative = AdCreative::where('campaign_key', 'promo-s2c')->firstOrFail();

        $response = $this->asAdmin()->put('/admin/publicidad/'.$creative->id, $this->payload([
            'name' => 'Promo editada',
        ]));
        $this->assertRedirectsToIndex($response);

        $response = $this->asAdmin()->delete('/admin/publicidad/'.$creative->id);
        $this->assertRedirectsToIndex($response);
        $this->assertSame('ended', $creative->fresh()->status);
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

    private function seedStats(AdCreative $creative, $date, $impressions, $clicks)
    {
        AdStatDaily::create([
            'creative_id' => $creative->id,
            'placement_id' => $creative->placement_id,
            'date' => $date,
            'impressions' => $impressions,
            'clicks' => $clicks,
        ]);
    }

    private function payload(array $overrides = [])
    {
        return array_merge([
            'campaign_key' => 'promo-s2c',
            'name' => 'Promo S2C',
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
