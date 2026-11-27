<?php

namespace Tests\Feature\Analytics;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_new_analytics_tables_have_the_contracted_columns()
    {
        $this->assertSame([
            'id',
            'producto_id',
            'date',
            'visits',
            'unique_visits',
            'clicks',
            'created_at',
            'updated_at',
        ], Schema::getColumnListing('post_stats_daily'));

        $this->assertSame([
            'id',
            'producto_id',
            'date',
            'identity',
            'source',
            'referer',
            'user_agent',
            'ip_hash',
            'created_at',
        ], Schema::getColumnListing('post_clicks'));

        $this->assertSame([
            'id',
            'producto_id',
            'date',
            'identity',
            'created_at',
        ], Schema::getColumnListing('post_visit_identities'));
    }

    public function test_daily_stats_unique_key_rejects_duplicates_and_supports_upsert()
    {
        $productoId = $this->makeProduct();
        $row = [
            'producto_id' => $productoId,
            'date' => '2026-11-24',
            'visits' => 1,
            'unique_visits' => 1,
            'clicks' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('post_stats_daily')->insert($row);

        $duplicateFailed = false;

        try {
            DB::table('post_stats_daily')->insert($row);
        } catch (\Illuminate\Database\QueryException $e) {
            $duplicateFailed = true;
        }

        $this->assertTrue($duplicateFailed, 'The unique (producto_id, date) must reject duplicates.');

        $upsert = 'INSERT INTO post_stats_daily '
            .'(producto_id, date, visits, unique_visits, clicks, created_at, updated_at) '
            .'VALUES (?, ?, 1, 1, 0, ?, ?) '
            .'ON DUPLICATE KEY UPDATE visits = visits + 1, unique_visits = unique_visits + VALUES(unique_visits)';

        DB::affectingStatement($upsert, [
            $productoId,
            '2026-11-24',
            now()->toDateTimeString(),
            now()->toDateTimeString(),
        ]);

        $stats = DB::table('post_stats_daily')->where('producto_id', $productoId)->get();

        $this->assertCount(1, $stats);
        $this->assertSame(2, (int) $stats->first()->visits);
        $this->assertSame(2, (int) $stats->first()->unique_visits);
    }

    public function test_visit_identity_unique_key_rejects_duplicates()
    {
        $productoId = $this->makeProduct();
        $row = [
            'producto_id' => $productoId,
            'date' => '2026-11-24',
            'identity' => 'ip:'.str_repeat('a', 64),
            'created_at' => now(),
        ];

        DB::table('post_visit_identities')->insert($row);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('post_visit_identities')->insert($row);
    }

    public function test_models_map_to_the_new_tables()
    {
        $productoId = $this->makeProduct();

        $stat = \App\PostStatDaily::create([
            'producto_id' => $productoId,
            'date' => '2026-11-24',
            'visits' => 1,
            'unique_visits' => 1,
            'clicks' => 0,
        ]);

        $click = \App\PostClick::create([
            'producto_id' => $productoId,
            'date' => '2026-11-24',
            'identity' => 'ip:'.str_repeat('b', 64),
            'source' => 'web',
            'referer' => null,
            'user_agent' => null,
            'ip_hash' => str_repeat('b', 64),
        ]);

        $identity = \App\PostVisitIdentity::create([
            'producto_id' => $productoId,
            'date' => '2026-11-24',
            'identity' => 'ip:'.str_repeat('c', 64),
        ]);

        $this->assertSame('post_stats_daily', $stat->getTable());
        $this->assertSame('post_clicks', $click->getTable());
        $this->assertSame('post_visit_identities', $identity->getTable());
        $this->assertSame(1, DB::table('post_stats_daily')->where('id', $stat->id)->count());
        $this->assertSame(1, DB::table('post_clicks')->where('id', $click->id)->count());
        $this->assertSame(1, DB::table('post_visit_identities')->where('id', $identity->id)->count());
    }
}
