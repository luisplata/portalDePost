<?php

use App\Http\Controllers\GraficaApiController;
use App\Http\Controllers\PPV;
use App\Producto;
use App\Stream;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::get('grafica', [GraficaApiController::class, 'index']);// TazaDeConvercion
Route::get('TazaDeConvercion', [GraficaApiController::class, 'TazaDeConvercion']);
Route::get('VisitsVsClicks', [GraficaApiController::class, 'VisitasVsClicks']);
Route::get('registrarVisita/{id}', [PPV::class, 'RegisterVisit']);
Route::get("infiniteScroll",function(){
    $paginate = Producto::PostOfPacks();
    return view("ScrollInfinite",["packs"=>$paginate]);
});
Route::get("infiniteScrollStream",function(){
    $paginate = Stream::GetFirstStreams();
    return view("ScrollInfiniteStream",["packs"=>$paginate]);
});

Route::get("search",function(Request $request){
    $search = $request->input("search");
    $paginate = Producto::Search($search);
    return response()->json($paginate);
});