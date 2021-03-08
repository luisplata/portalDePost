<?php

namespace App\Http\Controllers;

use App\LogVisit;
use Illuminate\Http\Request;
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

}
