<?php

use App\Http\Controllers\AdClickController;
use App\Http\Controllers\AdCreativeController;
use App\Http\Controllers\AdMetricsController;
use App\Http\Controllers\AdPlacementController;
use App\Http\Controllers\AnaliticaController;
use App\Http\Controllers\CanalController;
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

        // Metricas de analitica de publicaciones (REQ-S3-06, REQ-S4-01). Solo
        // GET y antes de los resources para no chocar con ellos.
        Route::get("analitica", [AnaliticaController::class, "index"]);
        Route::get("analitica/metricas", [AnaliticaController::class, "metricas"]);

        // Canales de publicacion (REQ-CAN-01..13). Declaradas antes del
        // resource para que {id} con where numerico no capture create.
        Route::get("canales", [CanalController::class, "index"]);
        Route::get("canales/create", [CanalController::class, "create"]);
        Route::post("canales", [CanalController::class, "store"]);
        Route::get("canales/{id}/edit", [CanalController::class, "edit"])->where("id", "[0-9]+");
        Route::put("canales/{id}", [CanalController::class, "update"])->where("id", "[0-9]+");
        Route::post("canales/{id}/estado", [CanalController::class, "estado"])->where("id", "[0-9]+");
        Route::post("canales/{id}/probar", [CanalController::class, "probar"])->where("id", "[0-9]+");
        Route::delete("canales/{id}", [CanalController::class, "destroy"])->where("id", "[0-9]+");

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

        // Series diarias para las graficas del listado (REQ-MET-01, REQ-MET-02).
        // Va antes del resource para no chocar con publicidad/{id}/edit.
        Route::get("publicidad/metrics", [AdMetricsController::class, "show"]);

        // CRUD de creatividades (REQ-ADM-01, D-01, D-03). El listado con
        // metricas (S2c) se declara antes del resource; show no se expone.
        Route::get("publicidad", [AdCreativeController::class, "index"]);
        Route::resource("publicidad", "AdCreativeController")->except(["show"]);
    });
    Route::resource("admin", "AdminController");
});