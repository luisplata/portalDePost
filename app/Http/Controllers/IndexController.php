<?php

namespace App\Http\Controllers;

use App\Services\ProductoService;

class IndexController extends Controller
{
    protected $productoService;

    public function __construct(ProductoService $productoService)
    {
        $this->productoService = $productoService;
    }

    public function index()
    {
        return view('welcome', $this->productoService->getDataForHomepage());
    }
}
