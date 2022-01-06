<?php

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
    });
    Route::resource("admin", "AdminController");
});