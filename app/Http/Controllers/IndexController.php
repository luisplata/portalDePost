<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Producto;

class IndexController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //necesitamos listar:
        /*
        banner de 6 packs 
        hot de 3 packs
        popular de 3 packs
        packs de 9 _listado_
        */
        $banner = Producto::where("estado","1")->limit(6)->get();
        $hot = Producto::where("estado","1")->limit(3)->get();
        $popular = Producto::where("estado","1")->limit(3)->get();
        $packs = Producto::where("estado","1")->limit(9)->get();
        return view('welcome',[
            "banners"=>$banner,
            "hot"=>$hot,
            "popular"=>$popular,
            "packs"=>$packs
        ]);
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
