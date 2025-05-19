<?php

use App\ConfigPublicity;
use App\Http\Controllers\GraficaApiController;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\PPV;
use App\Producto;
use App\Stream;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductoClientApiController;
use App\Http\Controllers\Api\PPVApiController;
use App\Http\Controllers\Api\ProductoDataController;

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
Route::get("infiniteScroll", function () {
    $paginate = Producto::PostOfPacks();
    return view("ScrollInfinite", ["packs" => $paginate]);
});
Route::get("infiniteScrollStream", function () {
    $paginate = Stream::GetFirstStreams();
    return view("ScrollInfiniteStream", ["packs" => $paginate]);
});

Route::get("/search/{search}", function ($search) {
    $paginate = Producto::Search($search);
    return response()->json($paginate);
});

Route::get("publicity/image/{key}", [ConfigPublicity::class, "GetImage"]);


// UPDATE: PUBLISH ALL FEATURES TO API
Route::get('/home', [App\Http\Controllers\Api\IndexApiController::class, 'index']);
Route::get('/search/{keyword}', [App\Http\Controllers\Api\IndexApiController::class, 'search']);
Route::get('/banners', [ProductoDataController::class, 'banners']);
Route::get('/hot', [ProductoDataController::class, 'hot']);
Route::get('/popular', [ProductoDataController::class, 'popular']);
Route::get('/packs', [ProductoDataController::class, 'packs']);
Route::get('/tags', [ProductoDataController::class, 'tags']);

Route::prefix('ppv')->group(function () {
    Route::get('/', [PPVApiController::class, 'index']);
    Route::get('/{id}', [PPVApiController::class, 'show']);
    Route::post('/visit/{id}', [PPVApiController::class, 'registerVisit']);
    Route::get('/search/{keyword}', [PPVApiController::class, 'search']);
});

Route::prefix('model')->group(function () {
    Route::get('/{id}', [ProductoClientApiController::class, 'show']);
    Route::get('/redirect/id/{id}', [ProductoClientApiController::class, 'redirectById']);
    Route::get('/redirect/name/{name}', [ProductoClientApiController::class, 'redirectByName']);
});
