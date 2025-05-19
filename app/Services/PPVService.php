<?php

namespace App\Services;

use App\Producto;
use App\Stream;

class PPVService
{
    public function getTags()
    {
        $tags = [];

        foreach (Producto::all() as $p) {
            $tags = array_merge($tags, explode('-', $p->tags));
        }

        foreach (Stream::all() as $p) {
            $tags = array_merge($tags, explode('-', $p->tags));
        }

        return array_unique($tags);
    }

    public function getIndexData()
    {
        return [
            "packs" => Stream::GetFirstStreams(),
            "other" => $this->getTags()
        ];
    }

    public function getStreamByNombre($slug)
    {
        $nombre = str_replace("-", " ", $slug);
        $nombre = trim($nombre);

        $stream = Stream::where("nombre", $nombre)->firstOrFail();
        $stream->CreateLog();

        return [
            "stream" => $stream,
            "streams" => Stream::GetFirstStreams(),
            "tags" => explode("-", $stream->tags),
            "other" => $this->getTags()
        ];
    }

    public function registrarVisita($id)
    {
        $stream = Stream::findOrFail($id);
        $stream->CreateVisit();
        return true;
    }

    public function buscar($keyword)
    {
        $keyword = strtolower($keyword);

        return [
            "posts" => Producto::whereRaw('LOWER(`tags`) like ?', ["%$keyword%"])->get(),
            "postNames" => Producto::whereRaw('LOWER(`nombre`) like ?', ["%$keyword%"])->get(),
            "streams" => Stream::whereRaw('LOWER(`tags`) like ?', ["%$keyword%"])->get(),
            "streamNames" => Stream::whereRaw('LOWER(`nombre`) like ?', ["%$keyword%"])->get(),
            "other" => $this->getTags()
        ];
    }
}
