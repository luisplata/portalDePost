<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ProductoClientService;

class ProductoClientCntroller extends Controller
{
    protected $service;

    public function __construct(ProductoClientService $service)
    {
        $this->service = $service;
    }

    public function show($id)
    {
        $data = $this->service->showProductoPorNombre($id);
        return view('client.client', $data);
    }

    public function Redirect($url)
    {
        return redirect($this->service->redirigirPorId($url));
    }

    public function RedirectName($name)
    {
        return redirect($this->service->redirigirPorNombre($name));
    }
}
