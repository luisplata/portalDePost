<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductoClientService;

class ProductoClientApiController extends Controller
{
    protected $service;

    public function __construct(ProductoClientService $service)
    {
        $this->service = $service;
    }

    public function show($id)
    {
        return response()->json($this->service->showOnlyProductoPorNombre($id));
    }

    public function redirectById($id)
    {
        return response()->json([
            'redirect_to' => $this->service->redirigirPorId($id)
        ]);
    }

    public function redirectByName($name)
    {
        return response()->json([
            'redirect_to' => $this->service->redirigirPorNombre($name)
        ]);
    }
}
