<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PPV extends Controller
{
    private function GetTags(){
        $post = \App\Producto::all();
        $streams = \App\Stream::all();

        $result = [];
        foreach ($post as $p){
            $tags = explode("-", $p->tags);
            foreach ($tags as $tag){
                $result[] = $tag;
            }
        }
        foreach ($streams as $p){
            $tags = explode("-", $p->tags);
            foreach ($tags as $tag){
                $result[] = $tag;
            }
        }
        return array_unique($result);
    }
    public function index()
    {
        return view("PPV.index", [
            "packs" => \App\Stream::GetFirstStreams(),
            "other"=>$this->GetTags()
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
            "tags" => explode("-", $stream->tags),
            "other"=>$this->GetTags()
        ]);

    }

    public function RegisterVisit($id)
    {
        $stream = \App\Stream::Find($id);
        $stream->CreateVisit();
    }

    public function search($work)
    {
        $post = \App\Producto::whereRaw('LOWER(`tags`) like ?', ['%' . strtolower($work) . '%'])->get();
        $streams = \App\Stream::whereRaw('LOWER(`tags`) like ?', ['%' . strtolower($work) . '%'])->get();
        $postName = \App\Producto::whereRaw('LOWER(`nombre`) like ?', ['%' . strtolower($work) . '%'])->get();
        $streamsName = \App\Stream::whereRaw('LOWER(`nombre`) like ?', ['%' . strtolower($work) . '%'])->get();
        return view('Search.Searching', [
            "posts" => $post,
            "postNames" => $postName,
            "streams" => $streams,
            "streamNames" => $streamsName,
            "other"=>$this->GetTags()
        ]);
    }
}
