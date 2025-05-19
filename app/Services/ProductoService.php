<?php

namespace App\Services;

use App\Producto;
use App\Stream;

class ProductoService
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

    public function getDataForHomepage()
    {
        return [
            "banners" => Producto::PostOfBanner(),
            "hot" => Producto::PostOfHot(),
            "popular" => Producto::PostOfPopular(),
            "packs" => Producto::PostOfPacks(),
            "other" => $this->getTags()
        ];
    }
}
