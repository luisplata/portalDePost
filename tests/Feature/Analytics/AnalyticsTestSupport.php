<?php

namespace Tests\Feature\Analytics;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Helpers compartidos por los tests de caracterizacion de analitica.
 *
 * El nombre no termina en `Test.php` a proposito: PHPUnit no debe ejecutar
 * esta clase como una suite. Las clases hijas son las que usan
 * `RefreshDatabase`.
 */
abstract class AnalyticsTestSupport extends TestCase
{
    /**
     * Sesion de admin que acepta el middleware `logeado`.
     *
     * @return $this
     */
    protected function asAdmin()
    {
        return $this->withSession(['admin' => (object) ['nombre' => 'tester']]);
    }

    /**
     * Inserta una categoria y devuelve su id.
     */
    protected function makeCategoria(): int
    {
        return DB::table('categorias')->insertGetId([
            'nombre' => 'Categoria '.uniqid(),
            'descripcion' => 'Categoria de prueba',
            'padre' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Inserta un producto con los defaults del dominio y devuelve su id.
     */
    protected function makeProduct(array $attributes = []): int
    {
        return DB::table('productos')->insertGetId(array_merge([
            'nombre' => 'Producto '.uniqid(),
            'imagen' => 'https://cdn.example/product.jpg',
            'categorias_id' => $this->makeCategoria(),
            'estado' => 1,
            'NombreLink' => 'PCK 1',
            'hotLink' => 'https://example.com/hot',
            'publication_date' => now()->subDay(),
            'isVideo' => '0',
            'url_video' => '',
            'tags' => 'alpha',
            'published' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    /**
     * Inserta una fila legacy de contadores para un producto.
     */
    protected function makeVisitPost(int $productoId, int $visita = 0, int $idoAlPack = 0): int
    {
        return DB::table('visit_posts')->insertGetId([
            'producto_id' => $productoId,
            'visita' => $visita,
            'idoAlPack' => $idoAlPack,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Inserta una visita legacy en `log_visits`.
     */
    protected function makeLogVisit(int $productoId): int
    {
        return DB::table('log_visits')->insertGetId([
            'producto_id' => $productoId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Devuelve las claves de un item en orden alfabetico para comparar sets.
     */
    protected function keysOf(array $item): array
    {
        $keys = array_keys($item);
        sort($keys);

        return $keys;
    }
}
