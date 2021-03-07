<?php

namespace App\Http\Controllers;

use App\Producto;
use Illuminate\Http\Request;

class ProductoClientCntroller extends Controller
{

    public function show($id)
    {
        //desconvertir
        $id = str_replace("-", " ", $id);
        return view('client.client',[
            "banners"=>Producto::PostOfBanner(),
            "hot"=>Producto::PostOfHot(),
            "popular"=>Producto::PostOfPopular(),
            "packs"=>Producto::PostOfPacks(),
            "post"=>Producto::where("nombre",$id)->first()
        ]);
    }
}
