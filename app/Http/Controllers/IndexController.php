<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Producto;

class IndexController extends Controller
{
    public function index()
    {
        //necesitamos listar:
        /*
        banner de 6 packs 
        hot de 3 packs
        popular de 3 packs
        packs de 9 _listado_
        */
        return view('welcome',[
            "banners"=>Producto::PostOfBanner(),
            "hot"=>Producto::PostOfHot(),
            "popular"=>Producto::PostOfPopular(),
            "packs"=>Producto::PostOfPacks()
        ]);
    }
}
