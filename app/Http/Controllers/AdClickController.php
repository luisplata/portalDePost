<?php

namespace App\Http\Controllers;

use App\AdCreative;
use App\AdPlacement;
use App\ConfigPublicity;
use App\Services\AdService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Click de publicidad y aliases legacy (REQ-CLK-01..04).
 *
 * - `redirect`: creatividad servible -> click + 302; existente pero no
 *   servible -> 302 sin contar (D-06); inexistente -> 404.
 * - `legacy`: alias de config('ads.legacy_aliases') -> delega en `redirect`;
 *   sin mapeo -> config_publicities (302 sin click, D-07); nada -> 404.
 *
 * Siempre 302 (nunca 301) y Cache-Control no-store para no envenenar
 * caches de navegador con un destino que puede cambiar.
 */
class AdClickController extends Controller
{
    protected $adService;

    public function __construct(AdService $adService)
    {
        $this->adService = $adService;
    }

    /**
     * GET /ads/{campaign_key}
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $campaignKey
     * @return \Illuminate\Http\Response
     */
    public function redirect(Request $request, $campaignKey)
    {
        $creative = AdCreative::query()
            ->where('campaign_key', $campaignKey)
            ->first();

        if ($creative === null) {
            abort(404);
        }

        $placement = AdPlacement::query()->find($creative->placement_id);

        $servable = $placement !== null
            ? $this->adService->isServable($creative->campaign_key, $placement->key)
            : null;

        if ($servable !== null) {
            try {
                $this->adService->recordClick(
                    $servable->creative_id,
                    $servable->placement_id,
                    $request->cookie('anonymous_user_id'),
                    $request->ip(),
                    $request->headers->get('referer'),
                    $request->userAgent()
                );
            } catch (\Throwable $e) {
                // El enlace ya distribuido no debe romperse por un fallo de
                // tracking: se loguea y se redirige igual.
                Log::error('ads: no se pudo registrar el click', [
                    'campaign_key' => $campaignKey,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()
            ->away($this->adService->safeTargetUrl($creative->target_url))
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    /**
     * GET /publicity/{key}
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $key
     * @return \Illuminate\Http\Response
     */
    public function legacy(Request $request, $key)
    {
        $aliases = (array) config('ads.legacy_aliases', []);

        if (isset($aliases[$key])) {
            return $this->redirect($request, (string) $aliases[$key]);
        }

        $publicity = ConfigPublicity::query()->where('key', $key)->first();

        if ($publicity === null) {
            abort(404);
        }

        $value = $publicity->GetValue();
        $url = (is_object($value) && isset($value->url)) ? (string) $value->url : '';

        if ($url === '') {
            abort(404);
        }

        // Compatibilidad pura: enlace legacy sin atribucion (D-07).
        return redirect()->away($url);
    }
}
