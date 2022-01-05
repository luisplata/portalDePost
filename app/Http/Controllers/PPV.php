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
        return view("PPV.index",[
            "packs"=>\App\Stream::GetFirstStreams()
        ]);
    }
    public function show($id)
    {
        //desconvertir
        $id = str_replace("-", " ", $id);
        $stream = \App\Stream::where("nombre",$id)->first();
        $stream->CreateLog();
        return view('client.stream',[
            "stream"=>$stream,
            "streams"=>\App\Stream::GetFirstStreams(),
            "tags"=>explode("-", $stream->tags)
        ]);

    }

    public function RegisterVisit($id){
        $stream = \App\Stream::Find($id);
        $stream->CreateVisit();
    }
}
