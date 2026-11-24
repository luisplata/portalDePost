<?php

namespace Tests\Feature\Ads;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Contrato de REQ-PLC-01, REQ-CMP-01 y REQ-CMP-04.
 *
 * No usa RefreshDatabase: testeamos DDL real (rollback de las 5 migraciones),
 * que hace commit implicito en MySQL. Cada test arranca con `migrate:fresh` y
 * NO se revierte el batch al terminar, para que las clases siguientes
 * encuentren el esquema completo.
 */
class MigrationIdempotencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    public function test_seeds_the_four_contract_placements_with_their_limits()
    {
        $placements = DB::table('ad_placements')->orderBy('key')->get();

        $this->assertCount(4, $placements);
        $this->assertSame(
            ['home_sidebar', 'ppv_sidebar', 'product_banner_grid', 'product_sidebar'],
            $placements->pluck('key')->all()
        );
        $this->assertEquals(
            [
                'home_sidebar' => 1,
                'ppv_sidebar' => 1,
                'product_banner_grid' => 6,
                'product_sidebar' => 1,
            ],
            $placements->pluck('max_items', 'key')->all()
        );

        foreach ($placements as $placement) {
            $this->assertEquals(1, $placement->is_active);
            $this->assertNotEmpty($placement->name);
        }
    }

    public function test_reseeding_placements_preserves_edited_max_items_and_does_not_duplicate()
    {
        DB::table('ad_placements')->where('key', 'home_sidebar')->update(['max_items' => 2]);

        $this->placementsMigration()->seedPlacements();
        $this->placementsMigration()->seedPlacements();

        $this->assertSame(4, DB::table('ad_placements')->count());
        $this->assertEquals(
            2,
            DB::table('ad_placements')->where('key', 'home_sidebar')->value('max_items')
        );
    }

    public function test_data_migration_copies_the_game_snapshot_into_the_three_sidebars()
    {
        $legacy = json_decode(DB::table('config_publicities')->where('key', 'game')->value('value'));
        $placementIds = DB::table('ad_placements')->pluck('id', 'key');
        $creatives = DB::table('ad_creatives')->orderBy('campaign_key')->get();

        $this->assertNotNull($legacy, 'The legacy game publicity fixture must stay valid JSON.');
        $this->assertCount(3, $creatives);
        $this->assertSame(
            ['game-home_sidebar', 'game-ppv_sidebar', 'game-product_sidebar'],
            $creatives->pluck('campaign_key')->all()
        );

        $slotByCampaignKey = [
            'game-home_sidebar' => 'home_sidebar',
            'game-ppv_sidebar' => 'ppv_sidebar',
            'game-product_sidebar' => 'product_sidebar',
        ];

        foreach ($creatives as $creative) {
            $this->assertSame('active', $creative->status);
            $this->assertEquals(0, $creative->priority);
            $this->assertSame($legacy->url, $creative->target_url);
            $this->assertSame($legacy->image, $creative->image_url);
            $this->assertSame($legacy->name, $creative->headline);
            $this->assertSame($legacy->name, $creative->alt_text);
            $this->assertSame('Ver más', $creative->cta_label);
            $this->assertNull($creative->body);
            $this->assertEquals(
                $placementIds[$slotByCampaignKey[$creative->campaign_key]],
                $creative->placement_id
            );
        }

        $this->assertSame(
            0,
            DB::table('ad_creatives')
                ->where('placement_id', $placementIds['product_banner_grid'])
                ->count(),
            'The legacy migration must not fill product_banner_grid.'
        );
    }

    public function test_data_migration_does_not_create_metrics_rows()
    {
        $this->assertSame(0, DB::table('ad_stats_daily')->count());
        $this->assertSame(0, DB::table('ad_clicks')->count());
    }

    public function test_rerunning_the_data_migration_does_not_duplicate_creatives()
    {
        $this->legacyMigration()->up();
        $this->legacyMigration()->up();

        $this->assertSame(3, DB::table('ad_creatives')->count());
        $this->assertSame(
            1,
            DB::table('ad_creatives')->where('campaign_key', 'game-home_sidebar')->count()
        );
    }

    public function test_data_migration_survives_invalid_legacy_json()
    {
        DB::table('ad_creatives')->delete();
        DB::table('config_publicities')
            ->where('key', 'game')
            ->update(['value' => '{ this is not valid json']);

        $this->legacyMigration()->up();

        $this->assertSame(0, DB::table('ad_creatives')->count());
        $this->assertTrue(Schema::hasTable('ad_creatives'));
    }

    public function test_rollback_removes_the_ad_tables_and_keeps_the_legacy_tables()
    {
        $this->assertTrue(Schema::hasTable('ad_clicks'));

        // El step se calcula desde la primera migracion de ads hasta la ultima
        // migrada: nuevas migraciones aditivas (p. ej. analitica) corren despues
        // y un numero fijo dejaria de cubrir el batch de ads.
        $step = DB::table('migrations')
            ->where('migration', '>=', '2026_11_24_000001_create_ad_placements_table')
            ->count();

        try {
            $this->artisan('migrate:rollback', ['--step' => $step, '--force' => true])
                ->assertExitCode(0);

            foreach (['ad_clicks', 'ad_stats_daily', 'ad_creatives', 'ad_placements'] as $table) {
                $this->assertFalse(Schema::hasTable($table), "{$table} must be gone after rollback.");
            }

            $this->assertTrue(Schema::hasTable('config_publicities'));
            $this->assertTrue(Schema::hasTable('publicities'));
            $this->assertSame(1, DB::table('config_publicities')->where('key', 'game')->count());
        } finally {
            // DDL no vuelve atras con transacciones: se restaura el esquema para
            // que las clases siguientes no dependan del orden de ejecucion.
            $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        }
    }

    public function test_ad_tables_use_mysql_57_compatible_column_types()
    {
        foreach (['ad_placements', 'ad_creatives', 'ad_stats_daily', 'ad_clicks'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} must exist.");
        }

        $jsonColumns = DB::select(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME LIKE 'ad\\_%'
               AND DATA_TYPE = 'json'"
        );

        $this->assertSame([], $jsonColumns, 'MySQL 5.7: no JSON columns allowed.');
    }

    private function placementsMigration(): \CreateAdPlacementsTable
    {
        require_once database_path('migrations/2026_11_24_000001_create_ad_placements_table.php');

        return new \CreateAdPlacementsTable();
    }

    private function legacyMigration(): \SeedAdLegacyCreatives
    {
        require_once database_path('migrations/2026_11_24_000005_seed_ad_legacy_creatives.php');

        return new \SeedAdLegacyCreatives();
    }
}
