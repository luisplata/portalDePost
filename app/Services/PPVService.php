<?php

namespace App\Services;

use App\Producto;
use App\Stream;
use App\Services\TagService;

class PPVService
{
    protected $tagService;

    public function __construct(TagService $tagService)
    {
        $this->tagService = $tagService;
    }

    // 🔹 Nuevos métodos reutilizables
    public function getFirstStreams()
    {
        return Stream::GetFirstStreams();
    }

    public function getStreamBySlug($slug)
    {
        $nombre = str_replace("-", " ", $slug);
        $nombre = trim($nombre);
        return Stream::where("nombre", $nombre)->firstOrFail();
    }

    public function getStreamTags($stream)
    {
        return explode('-', $stream->tags);
    }

    public function getPostsByTag($keyword)
    {
        return Producto::whereRaw('LOWER(`tags`) like ?', ["%$keyword%"])->get();
    }

    public function getPostsByName($keyword)
    {
        return Producto::whereRaw('LOWER(`nombre`) like ?', ["%$keyword%"])->get();
    }

    public function getStreamsByTag($keyword)
    {
        return Stream::whereRaw('LOWER(`tags`) like ?', ["%$keyword%"])->get();
    }

    public function getStreamsByName($keyword)
    {
        return Stream::whereRaw('LOWER(`nombre`) like ?', ["%$keyword%"])->get();
    }

    // 🔸 Métodos que usan los anteriores

    public function getIndexData()
    {
        return [
            "packs" => $this->getFirstStreams(),
            "other" => $this->tagService->getAllTags()
        ];
    }

    public function getStreamByNombre($slug)
    {
        $stream = $this->getStreamBySlug($slug);
        $stream->CreateLog();

        return [
            "stream" => $stream,
            "streams" => $this->getFirstStreams(),
            "tags" => $this->getStreamTags($stream),
            "other" => $this->tagService->getAllTags()
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
            "posts" => $this->getPostsByTag($keyword),
            "postNames" => $this->getPostsByName($keyword),
            "streams" => $this->getStreamsByTag($keyword),
            "streamNames" => $this->getStreamsByName($keyword),
            "other" =>  $this->tagService->getAllTags()
        ];
    }
}
