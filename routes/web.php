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

Route::get("/login", function() {
    return view("login");
});
Route::get("/logout", function() {
    session()->flush();
    return redirect("/login");
});
Route::post("/login", "LoginController@login");

Route::middleware('logeado')->group(function () {
    //para el admin
    Route::prefix('admin')->group(function () {
        Route::resource("/", "AdminController");
        Route::get("/{id}", "AdminController@show");
        Route::get("/{id}/edit", "AdminController@edit");
        Route::put("/{id}", "AdminController@update");
        Route::delete("/{id}", "AdminController@destroy");
    });
    Route::prefix('categoria')->group(function () {
        Route::resource("/", "CategoriaController");
        Route::get("/{id}", "CategoriaController@show");
        Route::get("/{id}/edit", "CategoriaController@edit");
        Route::put("/{id}", "CategoriaController@update");
        Route::delete("/{id}", "CategoriaController@destroy");
    });
    Route::prefix('producto')->group(function () {
        Route::resource("/", "ProductoController");
        Route::get("/{id}", "ProductoController@show");
        Route::get("/{id}/edit", "ProductoController@edit");
        Route::put("/{id}", "ProductoController@update");
        Route::delete("/{id}", "ProductoController@destroy");
    });
});
