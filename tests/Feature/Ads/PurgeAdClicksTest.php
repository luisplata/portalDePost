<?php

namespace Tests\Feature\Ads;

use App\AdCreative;
use App\AdPlacement;
use App\AdStatDaily;
use App\Services\AdMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Retencion del detalle de clicks (REQ-PRV-02, REQ-PRV-03).
 *
 * El corte se calcula en UTC sobre `created_at`; `ad_stats_daily` (agregados)
 * nunca se purga.
 */
class PurgeAdClicksTest extends TestCase
{
    use RefreshDatabase;

    /** @var \App\AdCreative */
    private $creative;

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase corre `migrate:fresh` via PendingCommand, que deja un
        // binding mockeado de OutputStyle en el contenedor del primer test del
        // proceso. Se elimina para que nuestros comandos escriban en el buffer
        // que capturamos.
        $this->app->offsetUnset(\Illuminate\Console\OutputStyle::class);

        DB::table('ad_creatives')->delete();
        Cache::flush();

        $this->creative = AdCreative::create([
            'campaign_key' => 'promo-purge',
            'name' => 'Promo Purge',
            'placement_id' => AdPlacement::where('key', 'home_sidebar')->firstOrFail()->id,
            'status' => 'active',
            'image_url' => 'https://cdn.example/purge.jpg',
            'headline' => 'Headline',
            'target_url' => 'https://example.com/purge',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_deletes_only_expired_clicks_and_reports_the_count()
    {
        $this->click(120);
        $this->click(120);
        $this->click(120);
        $this->click(1);
        $this->click(0);

        $output = new BufferedOutput;
        $this->assertSame(0, Artisan::call('ads:purge-clicks', [], $output));
        $this->assertStringContainsString('Deleted 3 clicks', $output->fetch());

        $this->assertSame(2, DB::table('ad_clicks')->count());
        $this->assertSame(0, DB::table('ad_clicks')->where('created_at', '<', Carbon::now('UTC')->subDays(90))->count());
    }

    public function test_command_does_not_touch_daily_stats()
    {
        $this->click(120);
        AdStatDaily::create([
            'creative_id' => $this->creative->id,
            'placement_id' => $this->creative->placement_id,
            'date' => Carbon::now('UTC')->subDays(120)->toDateString(),
            'impressions' => 40,
            'clicks' => 4,
        ]);

        $this->artisan('ads:purge-clicks')->assertExitCode(0);

        $this->assertSame(0, DB::table('ad_clicks')->count());
        $this->assertSame(1, DB::table('ad_stats_daily')->count());
    }

    public function test_command_is_idempotent_and_exits_zero_with_nothing_to_delete()
    {
        $this->click(120);

        $first = new BufferedOutput;
        $this->assertSame(0, Artisan::call('ads:purge-clicks', [], $first));
        $second = new BufferedOutput;
        $this->assertSame(0, Artisan::call('ads:purge-clicks', [], $second));

        $this->assertStringContainsString('Deleted 0 clicks', $second->fetch());
        $this->assertSame(0, DB::table('ad_clicks')->count());
    }

    public function test_days_option_overrides_the_retention()
    {
        $this->click(10);
        $this->click(2);

        $output = new BufferedOutput;
        $this->assertSame(0, Artisan::call('ads:purge-clicks', ['--days' => 5], $output));

        $this->assertStringContainsString('older than 5 days', $output->fetch());
        $this->assertSame(1, DB::table('ad_clicks')->count());
    }

    public function test_opportunistic_purge_runs_at_most_once_per_day()
    {
        $service = app(AdMetricsService::class);

        $this->click(120);
        $this->click(120);
        $this->assertSame(2, $service->purgeIfDue());

        // Ya hay marca en cache: la segunda pasada no vuelve a borrar.
        $this->click(120);
        $this->assertSame(0, $service->purgeIfDue());
        $this->assertSame(1, DB::table('ad_clicks')->count());
    }

    public function test_opportunistic_purge_never_throws()
    {
        $failing = new class extends AdMetricsService {
            public function purge($days = null)
            {
                throw new \RuntimeException('boom');
            }
        };

        $this->assertSame(0, $failing->purgeIfDue());
    }

    private function click($daysAgo)
    {
        DB::table('ad_clicks')->insert([
            'creative_id' => $this->creative->id,
            'placement_id' => $this->creative->placement_id,
            'created_at' => Carbon::now('UTC')->subDays($daysAgo),
        ]);
    }
}
