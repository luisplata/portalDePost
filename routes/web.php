<?php

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

Route::get('/', function () {
    return view('welcome');
});
Route::get('/quienes-somos', function () {
    return view('quienes_somos');
});
Route::get('/portafolio/{filtro?}', function ($filtro = null) {
//Aqui filtramos
//mandará un dato que sera el nombre del fintro y retornará los productos
//con esa categoria
    $datos = array(
        "filtro" => $filtro
    );
    return view('portafolio', $datos);
});

Route::get("/login", function() {
    return view("login");
});
Route::get("/logout", function() {
    session()->flush();
    return redirect("/login");
});
Route::post("/login", "LoginController@login");

Route::prefix('admin')->middleware('logeado')->group(function () {
    Route::resource("/", "AdminController");
});
