<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductoService;
use App\Services\PPVService;

class IndexApiController extends Controller
{
    protected $productoService;
    protected $ppvService;

    public function __construct(ProductoService $productoService, PPVService $ppvService)
    {
        $this->productoService = $productoService;
        $this->ppvService = $ppvService;
    }

    public function index()
    {
        return response()->json($this->productoService->getDataForHomepage());
    }

    public function search($keyword)
    {
        $keyword = strtolower($keyword);

        // Buscar productos
        $productsByTag = $this->productoService->getPostsByTag($keyword);
        $productsByName = $this->productoService->getPostsByName($keyword);

        // Buscar streams (PPV)
        $streamsByTag = $this->ppvService->getStreamsByTag($keyword);
        $streamsByName = $this->ppvService->getStreamsByName($keyword);

        // Combinar resultados (podés decidir si quieres hacer merge o devolver por separado)
        return response()->json([
            'products_by_tag' => $productsByTag,
            'products_by_name' => $productsByName,
            'streams_by_tag' => $streamsByTag,
            'streams_by_name' => $streamsByName,
        ]);
    }
}
