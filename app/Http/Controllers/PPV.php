<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PPVService;

class PPV extends Controller
{
    protected $service;

    public function __construct(PPVService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return view("PPV.index", $this->service->getIndexData());
    }

    public function show($id)
    {
        return view("client.stream", $this->service->getStreamByNombre($id));
    }

    public function RegisterVisit($id)
    {
        $this->service->registrarVisita($id);
    }

    public function search($work)
    {
        return view("Search.Searching", $this->service->buscar($work));
    }
}
