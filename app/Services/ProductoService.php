<?php

namespace App\Services;

use App\Producto;
use App\Stream;
use App\Services\TagService;

class ProductoService
{
    protected $tagService;

    public function __construct(TagService $tagService)
    {
        $this->tagService = $tagService;
    }
    public function getBanners()
    {
        return Producto::PostOfBanner();
    }

    public function getHot()
    {
        return Producto::PostOfHot();
    }

    public function getPopular()
    {
        return Producto::PostOfPopular();
    }

    public function getPacks()
    {
        return Producto::PostOfPacks();
    }

    public function getDataForHomepage()
    {
        return [
            "banners" => $this->getBanners(),
            "hot" => $this->getHot(),
            "popular" => $this->getPopular(),
            "packs" => $this->getPacks(),
            "other" => $this->tagService->getAllTags()
        ];
    }

    public function getPostsByTag(string $tag)
    {
        return Producto::where('estado', '1')
            ->where('publication_date', '<', now())
            ->whereRaw('LOWER(tags) like ?', ['%' . strtolower($tag) . '%'])
            ->orderBy('publication_date', 'desc')
            ->paginate(10);
    }

    public function getPostsByName(string $name)
    {
        return Producto::where('estado', '1')
            ->where('publication_date', '<', now())
            ->whereRaw('LOWER(nombre) like ?', ['%' . strtolower($name) . '%'])
            ->orderBy('publication_date', 'desc')
            ->paginate(10);
    }

}
