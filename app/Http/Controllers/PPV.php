<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PPV extends Controller
{
    public function index()
    {
        return view("PPV.index", [
            "packs" => \App\Stream::GetFirstStreams()
        ]);
    }

    public function show($id)
    {
        //desconvertir
        $id = str_replace("-", " ", $id);
        $id = trim($id);
        $stream = \App\Stream::where("nombre", $id)->first();
        $stream->CreateLog();
        return view('client.stream', [
            "stream" => $stream,
            "streams" => \App\Stream::GetFirstStreams(),
            "tags" => explode("-", $stream->tags)
        ]);

    }

    public function RegisterVisit($id)
    {
        $stream = \App\Stream::Find($id);
        $stream->CreateVisit();
    }

    public function search($work)
    {
        //dd($work);
        $post = [];
        $streams = [];
        $postName = [];
        $streamsName = [];

        $post = \App\Producto::whereRaw('LOWER(`tags`) like ?', ['%' . strtolower($work) . '%'])->get();
        $streams = \App\Stream::whereRaw('LOWER(`tags`) like ?', ['%' . strtolower($work) . '%'])->get();
        $postName = \App\Producto::whereRaw('LOWER(`nombre`) like ?', ['%' . strtolower($work) . '%'])->get();
        $streamsName = \App\Stream::whereRaw('LOWER(`nombre`) like ?', ['%' . strtolower($work) . '%'])->get();
        return view('Search.Searching', [
            "posts" => $post,
            "postNames" => $postName,
            "streams" => $streams,
            "streamNames" => $streamsName
        ]);
    }
}
