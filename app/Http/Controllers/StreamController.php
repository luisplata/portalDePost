<?php

namespace App\Http\Controllers;

use App\Stream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

class StreamController extends Controller
{
    public function Upload(){
        return view("admin.stream.upload");
    }

    public function UploadFile(Request $request){
        $file = $request->file('logo');
        $reader = Reader::createFromFileObject($file->openFile());
        // Create a customer from each row in the CSV file
        foreach ($reader as $index => $row) {
            $stream = new Stream();
            $stream->nombre = $row[0];
            $stream->imagen = $row[1];
            $stream->publication_date = $row[2];
            $stream->url = $row[3];
            $stream->tags = $row[4];
            $stream->estado =1;
            $stream->save();
        }
        return redirect('admin/stream');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        //
        $datos = array(
            "streams" => \App\Stream::orderBy('publication_date', 'desc')->get()
        );
        return view("admin.stream.dashboard", $datos);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
        //mostramos el formulario para crear el producto
        $datos = array();
        return view("admin.stream.create", $datos);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        //creamos el producto
        //recivimos el archivo
        try {
            
            $stream = new \App\Stream();
            $stream->nombre = $request->nombre;
            $stream->imagen = $request->image;
            $stream->publication_date = $request->publication_date;
            $stream->url = $request->url;
            $stream->tags = $request->tags;
            $stream->estado = 1;
            
            if ($stream->save()) {
                return redirect("admin/stream?1");
            } else {
                return redirect("admin/stream?2");
            }
        } catch (\Exception $ex) {
            dd($ex);
            return redirect("admin/stream?3");
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id) {
        //mostrasmo el producto en especifico
        $datos = array("stream" => \App\Stream::find($id));
        return view("admin.stream.view", $datos);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id) {
        //
        $stream = \App\Stream::find($id);
        $stream->checkedSi = $stream->isVideo == "1"? "checked" : "";
        $stream->checkedNo = $stream->isVideo == "0"? "checked" : "";
        $datos = array(
            "stream" => $stream
        );
        return view("admin.stream.edit", $datos);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        //actualizamos el articulo
        //buscamos el producto
        try {
            $stream = \App\Stream::find($id);
            $stream->nombre = $request->nombre;
            $stream->imagen = $request->imagen;
            $stream->publication_date = $request->publication_date;
            $stream->url = $request->url;
            $stream->estado =$request->estado;
            $stream->tags = $request->tags;
            
            if ($stream->save()) {
                return redirect("admin/stream?mensaje=Se modifico el stream con exito&tipo=success");
            } else {
                return redirect("admin/stream?mensaje=No se modifico el stream con exito&tipo=warning");
            }
        } catch (\Exception $ex) {
            dd($ex);
            return redirect("admin/stream?mensaje=Error&tipo=error");
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        //eliminamos el producto y los mandamos a la verga! :v
        try {
            $stream = \App\Stream::find($id);
            //Storage::delete([public_path().$stream->imagen]);
            if ($stream->delete()) {
                //eliminamos el archivo
                return redirect("admin/stream?mensaje=Se elimino el stream&tipo=success");
            } else {
                return redirect("admin/stream?mensaje=No se elimino el stream&tipo=warining");
            }
        } catch (\Exception $ex) {
            dd($ex);
            return redirect("admin/stream?mensaje=Error&tipo=error");
        }
    }
}
