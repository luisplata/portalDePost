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
        Route::resource("categoria", "CategoriaController");
        Route::resource("producto", "ProductoController");
    });
    Route::resource("admin", "AdminController");
});

Route::resource("product", "ProductoClientCntroller");
