<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\ProductoService;
use App\Services\PPVService;
use App\Producto;
use App\Stream;

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

    public function search(Request $request)
    {
        $query = strtolower($request->query('query', ''));
        $perPage = $request->query('per_page', 10);

        $productos = Producto::where(function ($q) use ($query) {
            $q->whereRaw('LOWER(nombre) LIKE ?', ["%$query%"])
                ->orWhereRaw('LOWER(tags) LIKE ?', ["%$query%"]);
        })
            ->where("estado", "1")
            ->where('publication_date', '<', now())
            ->orderBy('publication_date', 'desc')
            ->paginate($perPage, ['*'], 'productos_page');

        $streams = Stream::where(function ($q) use ($query) {
            $q->whereRaw('LOWER(nombre) LIKE ?', ["%$query%"])
                ->orWhereRaw('LOWER(tags) LIKE ?', ["%$query%"]);
        })
            ->where("estado", "1")
            ->where('publication_date', '<', now())
            ->orderBy('publication_date', 'desc')
            ->paginate($perPage, ['*'], 'streams_page');

        return response()->json([
            'productos' => $productos,
            'streams' => $streams,
        ]);
    }

    public function searchByTag(Request $request)
    {
        $tag = strtolower($request->query('tag', ''));
        $perPage = $request->query('per_page', 10);

        // Búsqueda en productos
        $productos = Producto::where("estado", "1")
            ->where('publication_date', '<', now())
            ->whereRaw('LOWER(tags) LIKE ?', ["%$tag%"])
            ->orderBy('publication_date', 'desc')
            ->paginate($perPage, ['*'], 'productos_page');

        // Búsqueda en streams
        $streams = Stream::where("estado", "1")
            ->where('publication_date', '<', now())
            ->whereRaw('LOWER(tags) LIKE ?', ["%$tag%"])
            ->orderBy('publication_date', 'desc')
            ->paginate($perPage, ['*'], 'streams_page');

        return response()->json([
            'productos' => $productos,
            'streams' => $streams,
        ]);
    }
}
