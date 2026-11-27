<?php

namespace App\Http\Controllers;

use App\AdCreative;
use App\AdPlacement;
use App\Producto;
use App\Services\AdMetricsService;
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
     * Listado con metricas de 30 dias en 2 queries: paginator con join a
     * placements (orden) + una agregacion por id para todo el lote (REQ-ADM-06).
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $metricsService = app(AdMetricsService::class);
        list($from, $to) = $metricsService->lastDays(30);

        // Purga oportunista de clicks vencidos, como maximo 1x/dia y sin
        // propagar errores al listado (REQ-PRV-03).
        $metricsService->purgeIfDue();

        $creatives = AdCreative::query()
            ->join('ad_placements', 'ad_placements.id', '=', 'ad_creatives.placement_id')
            ->orderBy('ad_placements.name')
            ->orderBy('ad_creatives.priority', 'desc')
            ->select(
                'ad_creatives.*',
                'ad_placements.name as placement_name',
                'ad_placements.key as placement_key',
                'ad_placements.is_active as placement_is_active'
            )
            ->paginate(20)
            ->withQueryString();

        return view('admin.Publicidad.index', [
            'creatives' => $creatives,
            'metrics' => $metricsService->summaryForCreatives($creatives->pluck('id')->all(), $from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

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

        return $this->listing('Creatividad creada');
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

        return $this->listing('Creatividad actualizada');
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

        return $this->listing('Creatividad finalizada');
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
     * Vuelve al listado con el mensaje flash que lee el layout (REQ-ADM-05).
     *
     * @param  string  $mensaje
     * @return \Illuminate\Http\Response
     */
    private function listing($mensaje)
    {
        return redirect(
            'admin/publicidad?mensaje='.rawurlencode($mensaje).'&tipo=success'
        );
    }
}
