<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductoController extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        //
        $datos = array(
            "productos" => \App\Producto::simplePaginate(15)
        );
        return view("producto.dashboard", $datos);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
        //mostramos el formulario para crear el producto
        $datos = array(
            "categorias" => \App\Categoria::all()
        );
        return view("producto.create", $datos);
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
            $producto = new \App\Producto();
            $producto->nombre = $request->nombre;
            $producto->descripcion = $request->descripcion;
            $producto->url = $request->url;
            $producto->categorias_id = $request->categoria_id;
            //$producto->imagen = $request->imagen->store('img');
            //validamos la imagen
            if ($request->file("imagen")) {
                //si tiene la imagen
                $file = $request->file("imagen");
                $nombre = "/images/productos/" . time() . "." . $file->getClientOriginalExtension();
                $path = public_path() . "/images/productos/";
                $file->move($path, $nombre);
            }
            $producto->imagen = $nombre;
            if ($producto->save()) {
                return redirect("producto?1");
            } else {
                return redirect("producto?2");
            }
        } catch (\Exception $ex) {
            dd($ex);
            return redirect("producto?3");
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
        $datos = array("producto" => \App\Producto::find($id));
        return view("producto.view", $datos);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id) {
        //
        $datos = array(
            "producto" => \App\Producto::find($id),
            "categorias" => \App\Categoria::all()
        );
        return view("producto.edit", $datos);
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
            $producto = \App\Producto::find($id);
            $producto->nombre = $request->nombre;
            $producto->descripcion = $request->descripcion;
            $producto->url = $request->url;
            $producto->categorias_id = $request->categoria_id;
            //clocando estado al producto
            $producto->estado = $request->estado;
            //validamos si modificamos la imagen
            if ($request->file("imagen")) {
                //si tiene la imagen
                $file = $request->file("imagen");
                $nombre = "/images/productos/" . time() . "." . $file->getClientOriginalExtension();
                $path = public_path() . "/images/productos/";
                $file->move($path, $nombre);
                $producto->imagen = $nombre;
            }
            if ($producto->save()) {
                return redirect("producto?mensaje=Se modifico el producto con exito&tipo=success");
            } else {
                return redirect("producto?mensaje=No se modifico el producto con exito&tipo=warning");
            }
        } catch (\Exception $ex) {
            dd($ex);
            return redirect("producto?mensaje=Error&tipo=error");
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
            $producto = \App\Producto::find($id);
            Storage::delete([public_path().$producto->imagen]);
            if ($producto->delete()) {
                //eliminamos el archivo
                return redirect("producto?mensaje=Se guardo el producto&tipo=success");
            } else {
                return redirect("producto?mensaje=No se guardo el producto&tipo=warining");
            }
        } catch (\Exception $ex) {
            dd($ex);
            return redirect("producto?mensaje=Error&tipo=error");
        }
    }

}
