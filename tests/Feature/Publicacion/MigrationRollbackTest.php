<?php

namespace Tests\Feature\Publicacion;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Rollback limpio de las tres migraciones aditivas (T-S1-09, REQ-REG-07).
 *
 * No usa RefreshDatabase: el DDL hace commit implicito en MySQL. Cada test
 * arranca con `migrate:fresh` y vuelve a migrar al terminar.
 */
class MigrationRollbackTest extends TestCase
{
    private const TABLAS_NUEVAS = [
        'publicacion_canales',
        'publicacion_ajustes',
        'publicacion_envios',
    ];

    private const PRODUCTOS_COLUMNS = [
        'id',
        'created_at',
        'updated_at',
        'nombre',
        'imagen',
        'categorias_id',
        'estado',
        'NombreLink',
        'hotLink',
        'publication_date',
        'isVideo',
        'url_video',
        'tags',
        'published',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    public function test_rollback_is_clean_and_leaves_existing_tables_untouched()
    {
        foreach (self::TABLAS_NUEVAS as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "{$tabla} must exist before rollback.");
        }

        try {
            $this->artisan('migrate:rollback', ['--step' => 3, '--force' => true])
                ->assertExitCode(0);

            foreach (self::TABLAS_NUEVAS as $tabla) {
                $this->assertFalse(Schema::hasTable($tabla), "{$tabla} must be gone after rollback.");
            }

            $this->assertTrue(Schema::hasTable('productos'));
            $this->assertSame(self::PRODUCTOS_COLUMNS, Schema::getColumnListing('productos'));
            $this->assertTrue(Schema::hasTable('autos'));
            $this->assertTrue(Schema::hasTable('visit_posts'));
        } finally {
            $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        }

        foreach (self::TABLAS_NUEVAS as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "{$tabla} must be back after re-migrating.");
        }
    }
}
