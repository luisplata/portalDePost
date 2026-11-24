<?php

use App\Http\Controllers\AdClickController;
use App\Http\Controllers\AdCreativeController;
use App\Http\Controllers\AdPlacementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

/*
  |--------------------------------------------------------------------------
  | Web Routes
  |--------------------------------------------------------------------------
  |
  | Here is where you can register web routes for your application. These
  | routes are loaded by the RouteServiceProvider within a group which
  | contains the "web" middleware group. Now create something great!
  |
 */

Route::get('/', "IndexController@index");

Route::get("content/{id}", "ProductoClientCntroller@show");

Route::get("redirect/{url}", "ProductoClientCntroller@Redirect");
Route::get("redirection/{url}", "ProductoClientCntroller@RedirectName");

Route::get("PPV", "PPV@index");

Route::get("PPV/{id}", "PPV@show");

Route::get("search/{work}", "PPV@search");

Route::get("/login", function () {
    return view("login");
});
Route::get("/logout", function () {
    session()->flush();
    return redirect("/login");
});
Route::post("/login", "LoginController@login");

// Publicidad: click atribuible (REQ-CLK-01, D-06) y alias legacy (REQ-CLK-04, D-07).
// Van despues de content/PPV/search y antes del grupo admin. El where evita
// capturar rutas con mayusculas, puntos o barras; admite `_` porque las
// creatividades legacy sembradas usan keys como `game-home_sidebar`.
Route::get("ads/{campaign_key}", [AdClickController::class, "redirect"])
    ->where("campaign_key", "[a-z0-9]+([_-][a-z0-9]+)*")
    ->middleware("throttle:ads-tracking");

Route::get("publicity/{key}", [AdClickController::class, "legacy"])
    ->middleware("throttle:ads-tracking");

Route::middleware('logeado')->group(function () {
    //para el admin
    Route::prefix('admin')->group(function () {

        Route::get("producto/upload", "ProductoController@Upload");
        Route::post("producto/uploadFile", "ProductoController@UploadFile")->name('admin.producto.uploadFile');
        Route::get("stream/upload", "StreamController@Upload");
        Route::post("stream/uploadFile", "StreamController@UploadFile")->name('admin.stream.uploadFile');
        Route::resource("categoria", "CategoriaController");
        Route::resource("producto", "ProductoController");
        Route::resource("stream", "StreamController");
        Route::resource("graficas", "GraficaController");

        // Administracion de placements (REQ-PLC-02, REQ-PLC-04). `key` es
        // inmutable (D-01) y no se lee del request: solo name/max_items/is_active.
        Route::get("placements", [AdPlacementController::class, "index"]);
        Route::put("placements/{key}", [AdPlacementController::class, "update"]);

        // CRUD de creatividades (REQ-ADM-01, D-01, D-03). El listado con
        // metricas (S2c) se declara antes del resource; show no se expone.
        Route::get("publicidad", [AdCreativeController::class, "index"]);
        Route::resource("publicidad", "AdCreativeController")->except(["show"]);
    });
    Route::resource("admin", "AdminController");
});