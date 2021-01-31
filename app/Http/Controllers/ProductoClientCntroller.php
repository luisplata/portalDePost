<?php

namespace App\Http\Controllers;

use App\Producto;
use Illuminate\Http\Request;

class ProductoClientCntroller extends Controller
{

    public function show($id)
    {
        //
        $banner = Producto::where("estado","1")->limit(6)->get();
        $hot = Producto::where("estado","1")->limit(3)->get();
        $popular = Producto::where("estado","1")->limit(3)->get();
        $packs = Producto::where("estado","1")->limit(9)->get();
        $postEspecifico = Producto::where("id",$id)->first();
        return view('client.client',[
            "banners"=>$banner,
            "hot"=>$hot,
            "popular"=>$popular,
            "packs"=>$packs,
            "post"=>$postEspecifico
        ]);
    }
}
