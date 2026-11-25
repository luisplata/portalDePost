<?php

namespace Tests\Feature\Publicacion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Esquema aditivo de publicaciones (T-S1-01..03, REQ-REG-07, ENT-03, CAN-06,
 * CAN-12).
 *
 * Ninguna migracion del cambio puede alterar tablas existentes. Este test fija
 * columnas, indices y comportamiento de las restricciones.
 */
class MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_publicacion_canales_table_has_expected_columns_and_indexes()
    {
        $this->assertTrue(Schema::hasTable('publicacion_canales'));

        $this->assertSame([
            'id',
            'tipo',
            'nombre',
            'habilitado',
            'vigente_desde',
            'credenciales',
            'config',
            'estado',
            'estado_detalle',
            'estado_verificado_at',
            'deleted_at',
            'created_at',
            'updated_at',
        ], Schema::getColumnListing('publicacion_canales'));

        $indexes = $this->indexesOf('publicacion_canales');

        $this->assertSame(['nombre'], $indexes['publicacion_canales_nombre_uniq']['columns']);
        $this->assertTrue($indexes['publicacion_canales_nombre_uniq']['unique']);

        $this->assertSame(
            ['habilitado', 'estado', 'deleted_at'],
            $indexes['publicacion_canales_operativo_idx']['columns']
        );
        $this->assertFalse($indexes['publicacion_canales_operativo_idx']['unique']);
    }

    public function test_publicacion_envios_table_has_pair_unique_and_foreign_keys()
    {
        $this->assertTrue(Schema::hasTable('publicacion_envios'));

        $this->assertSame([
            'id',
            'producto_id',
            'publicacion_canal_id',
            'canal_tipo',
            'estado',
            'intentos',
            'proximo_intento_at',
            'enviado_at',
            'referencia_externa',
            'http_status',
            'error',
            'created_at',
            'updated_at',
        ], Schema::getColumnListing('publicacion_envios'));

        $indexes = $this->indexesOf('publicacion_envios');

        $this->assertSame(
            ['producto_id', 'publicacion_canal_id'],
            $indexes['publicacion_envios_par_uniq']['columns']
        );
        $this->assertTrue($indexes['publicacion_envios_par_uniq']['unique']);

        $this->assertSame(
            ['publicacion_canal_id', 'estado', 'proximo_intento_at'],
            $indexes['publicacion_envios_canal_estado_idx']['columns']
        );
        $this->assertSame(
            ['estado', 'proximo_intento_at'],
            $indexes['publicacion_envios_estado_proximo_idx']['columns']
        );
        $this->assertSame(
            ['producto_id', 'estado'],
            $indexes['publicacion_envios_producto_estado_idx']['columns']
        );
        $this->assertSame(['created_at'], $indexes['publicacion_envios_created_idx']['columns']);

        $this->assertSame(
            'int(10) unsigned',
            $this->columnType('publicacion_envios', 'producto_id')
        );
        $this->assertSame(
            'bigint(20) unsigned',
            $this->columnType('publicacion_envios', 'publicacion_canal_id')
        );
    }

    public function test_publicacion_envios_rejects_a_duplicate_pair()
    {
        $producto = $this->insertProducto();
        $canal = $this->insertCanal();

        $this->insertEnvio($producto, $canal);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->insertEnvio($producto, $canal);
    }

    public function test_a_channel_with_history_cannot_be_deleted_physically()
    {
        $producto = $this->insertProducto();
        $canal = $this->insertCanal();
        $this->insertEnvio($producto, $canal);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('publicacion_canales')->where('id', $canal)->delete();
    }

    public function test_deleting_a_product_cascades_its_deliveries()
    {
        $producto = $this->insertProducto();
        $canal = $this->insertCanal();
        $this->insertEnvio($producto, $canal);

        DB::table('productos')->where('id', $producto)->delete();

        $this->assertSame(0, DB::table('publicacion_envios')->count());
        $this->assertSame(1, DB::table('publicacion_canales')->count());
    }

    private function columnType(string $table, string $column): string
    {
        $row = DB::selectOne(
            'SELECT COLUMN_TYPE AS column_type FROM information_schema.COLUMNS'
            .' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return $row->column_type;
    }

    private function insertProducto(): int
    {
        $categoria = DB::table('categorias')->insertGetId([
            'nombre' => 'Categoria '.uniqid(),
            'descripcion' => 'prueba',
            'padre' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('productos')->insertGetId([
            'nombre' => 'Producto '.uniqid(),
            'imagen' => 'https://cdn.example/product.jpg',
            'categorias_id' => $categoria,
            'estado' => 1,
            'NombreLink' => 'PCK 1',
            'hotLink' => 'https://example.com/hot',
            'publication_date' => now()->subDay(),
            'isVideo' => '0',
            'url_video' => '',
            'tags' => 'alpha',
            'published' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertEnvio(int $producto, int $canal): int
    {
        return DB::table('publicacion_envios')->insertGetId([
            'producto_id' => $producto,
            'publicacion_canal_id' => $canal,
            'canal_tipo' => 'telegram',
            'estado' => 'pendiente',
            'intentos' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_publicacion_ajustes_table_has_unique_clave()
    {
        $this->assertTrue(Schema::hasTable('publicacion_ajustes'));

        $this->assertSame([
            'id',
            'clave',
            'valor',
            'created_at',
            'updated_at',
        ], Schema::getColumnListing('publicacion_ajustes'));

        $indexes = $this->indexesOf('publicacion_ajustes');

        $this->assertSame(['clave'], $indexes['publicacion_ajustes_clave_uniq']['columns']);
        $this->assertTrue($indexes['publicacion_ajustes_clave_uniq']['unique']);
    }

    public function test_publicacion_ajustes_rejects_a_duplicate_clave()
    {
        DB::table('publicacion_ajustes')->insert([
            'clave' => 'publicaciones_enabled',
            'valor' => '0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('publicacion_ajustes')->insert([
            'clave' => 'publicaciones_enabled',
            'valor' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_publicacion_canales_rejects_a_duplicate_nombre_even_after_soft_delete()
    {
        $this->insertCanal(['nombre' => 'Telegram principal']);

        DB::table('publicacion_canales')->update(['deleted_at' => now()]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->insertCanal(['nombre' => 'Telegram principal']);
    }

    private function insertCanal(array $overrides = []): int
    {
        return DB::table('publicacion_canales')->insertGetId(array_merge([
            'tipo' => 'telegram',
            'nombre' => 'Canal '.uniqid(),
            'habilitado' => 0,
            'vigente_desde' => now(),
            'credenciales' => 'raw',
            'estado' => 'ok',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * @return array<string, array{columns: array<int, string>, unique: bool}>
     */
    private function indexesOf(string $table): array
    {
        $indexes = [];

        foreach (DB::select('SHOW INDEX FROM `'.$table.'`') as $row) {
            $name = $row->Key_name;

            if (!isset($indexes[$name])) {
                $indexes[$name] = ['columns' => [], 'unique' => false];
            }

            $indexes[$name]['columns'][(int) $row->Seq_in_index - 1] = $row->Column_name;
            $indexes[$name]['unique'] = (int) $row->Non_unique === 0;
        }

        return $indexes;
    }
}
