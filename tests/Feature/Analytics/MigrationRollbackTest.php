<?php

namespace Tests\Feature\Analytics;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Rollback limpio del slice S2a (T-S2a-07, REG-07).
 *
 * No usa RefreshDatabase: el DDL hace commit implicito en MySQL. Cada test
 * arranca con `migrate:fresh` y, al terminar, vuelve a migrar para que las
 * clases siguientes encuentren el esquema completo.
 */
class MigrationRollbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    public function test_rollback_step_three_removes_only_the_analytics_tables()
    {
        $this->assertTrue(Schema::hasTable('post_stats_daily'));
        $this->assertTrue(Schema::hasTable('post_clicks'));
        $this->assertTrue(Schema::hasTable('post_visit_identities'));

        try {
            $this->artisan('migrate:rollback', ['--step' => 3, '--force' => true])
                ->assertExitCode(0);

            foreach (['post_stats_daily', 'post_clicks', 'post_visit_identities'] as $table) {
                $this->assertFalse(Schema::hasTable($table), "{$table} must be gone after rollback.");
            }

            foreach (['productos', 'visit_posts', 'log_visits'] as $table) {
                $this->assertTrue(Schema::hasTable($table), "{$table} must survive the rollback.");
            }

            $this->assertSame(
                ['id', 'visita', 'idoAlPack', 'created_at', 'updated_at', 'producto_id'],
                Schema::getColumnListing('visit_posts')
            );
        } finally {
            $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        }

        $this->assertTrue(Schema::hasTable('post_stats_daily'));
    }
}
