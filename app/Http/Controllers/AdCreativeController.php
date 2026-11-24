<?php

namespace App\Http\Controllers;

use App\AdCreative;
use App\AdPlacement;
use App\Producto;
use App\Services\AdService;
use App\Http\Requests\AdCreativeRequest;

/**
 * CRUD admin de creatividades (REQ-ADM-01..08).
 *
 * - `campaign_key` es inmutable tras crear (D-01): el update nunca la escribe.
 * - `destroy` archiva (status ended), no borra: las metricas se conservan (D-03).
 * - Cada escritura invalida el slot nuevo y, si cambio, el anterior (REQ-ADM-07).
 */
class AdCreativeController extends Controller
{
    /**
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return $this->form(null);
    }

    /**
     * @param  \App\Http\Requests\AdCreativeRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(AdCreativeRequest $request)
    {
        $creative = AdCreative::create($request->validated());

        $this->forgetSlot($creative->placement_id);

        return $this->backToEdit($creative, 'Creatividad creada');
    }

    /**
     * @param  \App\AdCreative  $publicidad
     * @return \Illuminate\Http\Response
     */
    public function edit(AdCreative $publicidad)
    {
        return $this->form($publicidad);
    }

    /**
     * @param  \App\Http\Requests\AdCreativeRequest  $request
     * @param  \App\AdCreative  $publicidad
     * @return \Illuminate\Http\Response
     */
    public function update(AdCreativeRequest $request, AdCreative $publicidad)
    {
        // Se lee el slot anterior ANTES de persistir el cambio (REQ-ADM-07).
        $previousPlacementId = $publicidad->placement_id;

        $data = $request->validated();
        unset($data['campaign_key']); // D-01: el key no se reescribe jamas.

        $publicidad->fill($data)->save();

        $this->forgetSlot($previousPlacementId);

        if ((int) $publicidad->placement_id !== (int) $previousPlacementId) {
            $this->forgetSlot($publicidad->placement_id);
        }

        return $this->backToEdit($publicidad, 'Creatividad actualizada');
    }

    /**
     * Archiva la creatividad: status ended, sin borrar la fila (D-03).
     *
     * @param  \App\AdCreative  $publicidad
     * @return \Illuminate\Http\Response
     */
    public function destroy(AdCreative $publicidad)
    {
        $publicidad->status = 'ended';
        $publicidad->save();

        $this->forgetSlot($publicidad->placement_id);

        return $this->backToEdit($publicidad, 'Creatividad finalizada');
    }

    /**
     * @param  \App\AdCreative|null  $creative
     * @return \Illuminate\Http\Response
     */
    private function form(AdCreative $creative = null)
    {
        return view($creative === null ? 'admin.Publicidad.create' : 'admin.Publicidad.edit', [
            'creative' => $creative,
            'placements' => AdPlacement::orderBy('id')->get(),
            'productos' => Producto::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    /**
     * @param  int  $placementId
     * @return void
     */
    private function forgetSlot($placementId)
    {
        $placement = AdPlacement::find($placementId);

        if ($placement !== null) {
            app(AdService::class)->forgetSlot($placement->key);
        }
    }

    /**
     * S2c reemplazara este destino por el listado cuando exista.
     *
     * @param  \App\AdCreative  $creative
     * @param  string  $mensaje
     * @return \Illuminate\Http\Response
     */
    private function backToEdit(AdCreative $creative, $mensaje)
    {
        return redirect(
            'admin/publicidad/'.$creative->id.'/edit?mensaje='.rawurlencode($mensaje).'&tipo=success'
        );
    }
}
