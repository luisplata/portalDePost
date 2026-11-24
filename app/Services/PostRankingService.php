<?php

namespace App\Services;

use App\Producto;
use Illuminate\Support\Facades\DB;

/**
 * Ranking publico de publicaciones (productos).
 *
 * Devuelve modelos `Producto` con `id` igual a `productos.id` y los contadores
 * legacy agregados por producto. Una sola sentencia: los duplicados de
 * `visit_posts` se suman en una subconsulta y se unen con LEFT JOIN para que
 * un producto sin contadores aparezca con cero.
 */
class PostRankingService
{
    /**
     * Top por ratio de clicks. Ratio = idoAlPack / visita, 0 si visita = 0.
     *
     * @param  int  $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function hot($limit = 3)
    {
        return $this->normalize(
            $this->baseQuery()
                ->orderByRaw(
                    'CASE WHEN COALESCE(vp.visita, 0) > 0'
                    .' THEN COALESCE(vp.idoAlPack, 0) / COALESCE(vp.visita, 1)'
                    .' ELSE 0 END DESC'
                )
                ->orderByRaw('COALESCE(vp.visita, 0) DESC')
                ->orderBy('productos.id', 'asc')
                ->limit($limit)
                ->get()
        );
    }

    /**
     * Top por visitas, con clicks e id como desempate determinista.
     *
     * @param  int  $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function popular($limit = 3)
    {
        return $this->normalize(
            $this->baseQuery()
                ->orderByRaw('COALESCE(vp.visita, 0) DESC')
                ->orderByRaw('COALESCE(vp.idoAlPack, 0) DESC')
                ->orderBy('productos.id', 'asc')
                ->limit($limit)
                ->get()
        );
    }

    /**
     * Universo con contadores agregados: solo productos activos y publicados.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function baseQuery()
    {
        $aggregates = DB::table('visit_posts')
            ->select(
                'producto_id',
                DB::raw('SUM(visita) as visita'),
                DB::raw('SUM(idoAlPack) as idoAlPack')
            )
            ->groupBy('producto_id');

        return Producto::query()
            ->leftJoinSub($aggregates, 'vp', 'vp.producto_id', '=', 'productos.id')
            ->where('productos.estado', 1)
            ->where('productos.publication_date', '<', now())
            ->select(
                'productos.*',
                DB::raw('COALESCE(vp.visita, 0) as visita'),
                DB::raw('COALESCE(vp.idoAlPack, 0) as idoAlPack')
            );
    }

    /**
     * Normaliza tipos y garantiza `id === producto_id` sin depender del driver.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $productos
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function normalize($productos)
    {
        foreach ($productos as $producto) {
            $producto->id = (int) $producto->id;
            $producto->visita = (int) $producto->visita;
            $producto->idoAlPack = (int) $producto->idoAlPack;
            $producto->producto_id = $producto->id;
        }

        return $productos;
    }
}
