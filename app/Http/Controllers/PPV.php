<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PPV extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //dd(\App\Stream::GetFirstStreams());
        return view("PPV.index",[
            "packs"=>\App\Stream::GetFirstStreams()
        ]);
    }
    public function show($id)
    {
        //desconvertir
        $id = str_replace("-", " ", $id);
        $stream = \App\Stream::where("nombre",$id)->first();
        //$visita = $stream->Visitas;
        //if($visita == null){
            //$visita = new \App\VisitPost();
            //$visita->producto_id = $stream->id;
            //$visita->save();
        //}
        //$visita->AddVisita();
        
        //$log = new \App\LogVisit();
        //$log->producto_id = $stream->id;
        //$log->save();

        return view('client.stream',[
            "stream"=>$stream,
            "streams"=>\App\Stream::GetFirstStreams()
        ]);
    }
}
