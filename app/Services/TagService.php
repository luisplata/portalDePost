<?php

namespace App\Services;

use App\Producto;
use App\Stream;

class TagService
{
    public function getAllTags()
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
}
