<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PPVService;
use Illuminate\Http\Request;

class PPVApiController extends Controller
{
    protected $service;

    public function __construct(PPVService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return response()->json($this->service->getIndexData());
    }

    public function show($id)
    {
        return response()->json($this->service->getStreamByNombre($id));
    }

    public function registerVisit($id)
    {
        $this->service->registrarVisita($id);
        return response()->json(["message" => "Visita registrada"]);
    }

    public function search($keyword)
    {
        return response()->json($this->service->buscar($keyword));
    }
}
