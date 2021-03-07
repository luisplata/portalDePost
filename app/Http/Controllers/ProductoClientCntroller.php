<?php

namespace App\Http\Controllers;

use App\Producto;
use App\VisitPost;
use Illuminate\Http\Request;

class ProductoClientCntroller extends Controller
{

    public function show($id)
    {
        //desconvertir
        $id = str_replace("-", " ", $id);
        $product = Producto::where("nombre",$id)->first();
        $visita = $product->Visitas;
        if($visita == null){
            $visita = new VisitPost();
            $visita->producto_id = $product->id;
            $visita->save();
        }
        $visita->AddVisita();
        return view('client.client',[
            "banners"=>Producto::PostOfBanner(),
            "hot"=>Producto::PostOfHot(),
            "popular"=>Producto::PostOfPopular(),
            "packs"=>Producto::PostOfPacks(),
            "post"=>$product
        ]);
    }

    public function Redirect($url){
        $producto = Producto::where("id",$url)->first(); 
        $visita = $producto->Visitas;
        if($visita == null){
            $visita = new VisitPost();
            $visita->producto_id = $producto->id;
            $visita->save();
        }
        $visita->AddHotLink();
        return redirect($producto->hotLink);
    }

    public function RedirectName($name){
        $producto = Producto::where("nombre",$name)->first(); 
        $visita = $producto->Visitas;
        if($visita == null){
            $visita = new VisitPost();
            $visita->producto_id = $producto->id;
            $visita->save();
        }
        $visita->AddHotLink();
        return redirect($producto->hotLink);
    }
}
