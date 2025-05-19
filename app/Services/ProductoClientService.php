<?php

namespace App\Services;

use App\Producto;
use App\Stream;
use App\VisitPost;
use App\LogVisit;

class ProductoClientService
{
    public function getTags()
    {
        $tags = [];

        foreach (Producto::all() as $p) {
            $tags = array_merge($tags, explode('-', $p->tags));
        }

        foreach (Stream::all() as $p) {
            $tags = array_merge($tags, explode('-', $p->tags));
        }

        return array_unique($tags);
    }

    public function showProductoPorNombre($nombre)
    {
        $nombre = str_replace("-", " ", $nombre);
        $producto = Producto::where("nombre", $nombre)->firstOrFail();

        $this->registrarVisita($producto);
        $this->registrarLogVisita($producto);

        return [
            "banners" => Producto::PostOfBanner(),
            "hot" => Producto::PostOfHot(),
            "popular" => Producto::PostOfPopular(),
            "packs" => Producto::PostOfPacks(),
            "post" => $producto,
            "tags" => explode("-", $producto->tags),
            "other" => $this->getTags()
        ];
    }

    public function showOnlyProductoPorNombre($nombre)
    {
        // Intentar primero por nombre (limpiando guiones)
        $nombreLimpio = str_replace("-", " ", $nombre);
        $producto = Producto::whereRaw('LOWER(nombre) LIKE ?', ['%' . strtolower($nombreLimpio) . '%'])->first();

        // Si no se encontró por nombre, intentamos por ID
        if (!$producto && is_numeric($nombre)) {
            $producto = Producto::find($nombre);
        }

        // Si aún no se encontró, lanzar 404
        if (!$producto) {
            abort(404, "Producto no encontrado");
        }

        $this->registrarVisita($producto);
        $this->registrarLogVisita($producto);

        return $producto;
    }


    public function redirigirPorId($id)
    {
        $producto = Producto::findOrFail($id);
        $this->registrarHotlink($producto);
        return $producto->hotLink;
    }

    public function redirigirPorNombre($nombre)
    {
        $producto = Producto::where("nombre", $nombre)->firstOrFail();
        $this->registrarHotlink($producto);
        return $producto->hotLink;
    }

    private function registrarVisita($producto)
    {
        $visita = $producto->Visitas;

        if (!$visita) {
            $visita = new VisitPost();
            $visita->producto_id = $producto->id;
            $visita->save();
        }

        $visita->AddVisita();
    }

    private function registrarHotlink($producto)
    {
        $visita = $producto->Visitas;

        if (!$visita) {
            $visita = new VisitPost();
            $visita->producto_id = $producto->id;
            $visita->save();
        }

        $visita->AddHotLink();
    }

    private function registrarLogVisita($producto)
    {
        $log = new LogVisit();
        $log->producto_id = $producto->id;
        $log->save();
    }
}
