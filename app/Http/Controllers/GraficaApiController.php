<?php

namespace App\Http\Controllers;

use App\LogVisit;
use App\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class GraficaApiController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $datee = DB::select("select CAST(created_at AS DATE) as date_of_day, count(1) as count_of_day from `log_visits` where CAST(created_at AS DATE) = CAST(created_at AS DATE) group by CAST(created_at AS DATE)");
        return response()->json($datee);
    }

    public function TazaDeConvercion(){
        /*
        SELECT producto_id, visita, idoAlPack, ((idoAlPack/visita)*100) as porcentaje_efectividad FROM `visit_posts` v 
        ORDER BY (idoAlPack/visita)*100  DESC LIMIT 10
        */
        $datee = DB::select("SELECT producto_id, visita, idoAlPack, ((idoAlPack/visita)*100) as porcentaje_efectividad FROM `visit_posts` v ORDER BY (idoAlPack/visita)*100  DESC LIMIT 10");
        $consult = [];
        foreach($datee as $row){
            $producto = Producto::find($row->producto_id);
            $producto->visitas = $row->visita;
            $producto->clicks = $row->idoAlPack;
            $producto->porcentaje_efectividad = $row->porcentaje_efectividad;
            array_push($consult, $producto);
        }
        return response()->json($consult);
    }

    public function VisitasVsClicks(){
        $datee = DB::select("SELECT producto_id, visita, idoAlPack FROM `visit_posts` v ORDER BY visita  DESC LIMIT 10");
        $consult = [];
        foreach($datee as $row){
            $producto = Producto::find($row->producto_id);
            $producto->visitas = $row->visita;
            $producto->clicks = $row->idoAlPack;
            array_push($consult, $producto);
        }
        return response()->json($consult);
    }

}
