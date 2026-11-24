<?php

namespace Tests\Feature\Analytics;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Caracterizacion del esquema legacy (REG-07).
 *
 * Ninguna migracion de analitica puede alterar `productos`, `visit_posts` ni
 * `log_visits`. Este test congela el baseline de columnas.
 */
class MigrationTest extends AnalyticsTestSupport
{
    use RefreshDatabase;

    /**
     * Baseline de `visit_posts` (2021_03_07_164046).
     */
    private const VISIT_POSTS_COLUMNS = [
        'id',
        'visita',
        'idoAlPack',
        'created_at',
        'updated_at',
        'producto_id',
    ];

    /**
     * Baseline de `log_visits` (2021_03_08_143756).
     */
    private const LOG_VISITS_COLUMNS = [
        'id',
        'created_at',
        'updated_at',
        'producto_id',
    ];

    /**
     * Baseline de `productos` tras todas las migraciones aditivas.
     */
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

    public function test_legacy_tables_keep_their_baseline_columns()
    {
        $this->assertSame(self::VISIT_POSTS_COLUMNS, Schema::getColumnListing('visit_posts'));
        $this->assertSame(self::LOG_VISITS_COLUMNS, Schema::getColumnListing('log_visits'));
        $this->assertSame(self::PRODUCTOS_COLUMNS, Schema::getColumnListing('productos'));
    }
}
