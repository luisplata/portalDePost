<?php

namespace App\Http\Controllers;

use App\PublicacionEnvio;
use App\Services\Publicacion\PublicacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Admin de entregas (REQ-OBS-01..04, OBS-09).
 */
class PublicacionController extends Controller
{
    public function index(Request $request)
    {
        $estado = (string) $request->query('estado', '');
        $estados = ['pendiente', 'enviado', 'fallido', 'incierto'];

        $consulta = PublicacionEnvio::with(['canal', 'producto'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        if (in_array($estado, $estados, true)) {
            $consulta->where('estado', $estado);
        }

        if (ctype_digit((string) $request->query('canal'))) {
            $consulta->where('publicacion_canal_id', (int) $request->query('canal'));
        }

        if (ctype_digit((string) $request->query('producto'))) {
            $consulta->where('producto_id', (int) $request->query('producto'));
        }

        $fecha = (string) $request->query('fecha', '');

        if ($this->fechaValida($fecha)) {
            $consulta->whereDate('created_at', $fecha);
        }

        return view('admin.Publicacion.index', [
            'envios' => $consulta->paginate(20)->withQueryString(),
            'filtros' => [
                'estado' => in_array($estado, $estados, true) ? $estado : '',
                'canal' => (string) $request->query('canal', ''),
                'producto' => (string) $request->query('producto', ''),
                'fecha' => $this->fechaValida($fecha) ? $fecha : '',
            ],
        ]);
    }

    public function reintentar($id)
    {
        $envio = PublicacionEnvio::findOrFail($id);
        $resultado = app(PublicacionService::class)->reintentar($envio);

        return redirect('admin/publicaciones')->with(
            $resultado['ok'] ? 'mensaje' : 'error',
            $resultado['mensaje']
        );
    }

    private function fechaValida(string $fecha): bool
    {
        if ($fecha === '') {
            return false;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $fecha, 'UTC') !== false;
        } catch (\Exception $error) {
            return false;
        }
    }
}
