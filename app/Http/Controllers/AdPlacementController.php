<?php

namespace App\Http\Controllers;

use App\AdPlacement;
use App\Services\AdService;
use Illuminate\Http\Request;

/**
 * Administracion de placements (REQ-PLC-02, REQ-PLC-04).
 *
 * Solo se editan name, max_items e is_active. `key` es parte del contrato de
 * vistas y API: inmutable despues de crear (D-01), por eso el request no la
 * lee ni la escribe. Validacion inline por decision del design §5.
 */
class AdPlacementController extends Controller
{
    /**
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view("admin.Publicidad.placements", [
            "placements" => AdPlacement::orderBy("id")->get(),
        ]);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $key
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $key)
    {
        $placement = AdPlacement::where("key", $key)->firstOrFail();

        $datos = $request->validate([
            "name" => "required|string|max:100",
            "max_items" => "required|integer|between:1,10",
            "is_active" => "required|boolean",
        ]);

        $placement->name = $datos["name"];
        $placement->max_items = (int) $datos["max_items"];
        $placement->is_active = (bool) $datos["is_active"];
        $placement->save();

        app(AdService::class)->forgetSlot($placement->key);

        return redirect("admin/placements");
    }
}
