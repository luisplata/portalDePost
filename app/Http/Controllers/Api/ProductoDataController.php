<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductoService;
use App\Services\TagService;

class ProductoDataController extends Controller
{
    protected $service;
    protected $tagService;

    public function __construct(ProductoService $service, TagService $tagService)
    {
        $this->service = $service;
        $this->tagService = $tagService;
    }

    public function banners()
    {
        return response()->json($this->service->getBanners());
    }

    public function hot()
    {
        return response()->json($this->service->getHot());
    }

    public function popular()
    {
        return response()->json($this->service->getPopular());
    }

    public function packs()
    {
        return response()->json($this->service->getPacks());
    }

    public function tags()
    {
        return response()->json($this->tagService->getAllTags());
    }
}
