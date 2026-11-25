<?php

namespace App\Http\Controllers;

use App\Http\Requests\CanalRequest;
use App\PublicacionCanal;
use App\Services\Publicacion\AuditoriaPublicacion;
use App\Services\Publicacion\Credenciales;
use App\Services\Publicacion\PruebaCanal;
use App\Services\Publicacion\Secretos;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * CRUD de canales desde el admin (REQ-CAN-01..13, SEG-02/03/07, OBS-08).
 */
class CanalController extends Controller
{
    public function index()
    {
        return view('admin.Canal.index', [
            'canales' => PublicacionCanal::orderBy('id')->paginate(20),
            'etiquetas' => config('publicaciones.etiquetas'),
        ]);
    }

    public function create()
    {
        return view('admin.Canal.create', [
            'canal' => null,
            'drivers' => array_keys((array) config('publicaciones.drivers')),
        ]);
    }

    public function store(CanalRequest $request)
    {
        $tipo = (string) $request->input('tipo');
        $credenciales = $this->credencialesDelFormulario($tipo, $request);
        $resultado = Credenciales::validar($tipo, $credenciales);

        $canal = PublicacionCanal::create([
            'tipo' => $tipo,
            'nombre' => (string) $request->input('nombre'),
            'habilitado' => (bool) $request->input('habilitado', 0),
            'vigente_desde' => now(),
            'credenciales' => $credenciales,
            'estado' => $resultado['estado'],
            'estado_detalle' => $resultado['detalle'],
        ]);

        AuditoriaPublicacion::registrar('canal', 'alta', [
            'tipo',
            'nombre',
            'habilitado',
            'vigente_desde',
            'credenciales',
            'estado',
        ]);

        return redirect('admin/canales')
            ->with('mensaje', 'Canal creado: '.$canal->nombre);
    }

    public function edit($id)
    {
        $canal = PublicacionCanal::findOrFail($id);

        return view('admin.Canal.edit', [
            'canal' => $canal,
            'drivers' => array_keys((array) config('publicaciones.drivers')),
            'mascara' => Secretos::mascaraCanal($canal),
        ]);
    }

    public function update(CanalRequest $request, $id)
    {
        $canal = PublicacionCanal::findOrFail($id);

        $canal->nombre = (string) $request->input('nombre');

        $campos = ['nombre'];

        $tipo = $canal->tipo;
        $credenciales = $canal->credenciales;
        $cambiaronCredenciales = false;

        if ($tipo === 'telegram') {
            $token = trim((string) $request->input('token', ''));
            $chatId = trim((string) $request->input('chat_id', ''));

            if ($token !== '' && $chatId !== '') {
                $credenciales = ['token' => $token, 'chat_id' => $chatId];
                $cambiaronCredenciales = true;
            }
        } elseif ($tipo === 'discord') {
            $webhook = trim((string) $request->input('webhook_url', ''));

            if ($webhook !== '') {
                $credenciales = ['webhook_url' => $webhook];
                $cambiaronCredenciales = true;
            }
        }

        if ($cambiaronCredenciales) {
            $resultado = Credenciales::validar($tipo, $credenciales);
            $canal->credenciales = $credenciales;
            $canal->estado = $resultado['estado'];
            $canal->estado_detalle = $resultado['detalle'];
            $campos[] = 'credenciales';
            $campos[] = 'estado';
        }

        if ($request->filled('vigente_desde')) {
            $canal->vigente_desde = $this->parseFecha((string) $request->input('vigente_desde'));
            $campos[] = 'vigente_desde';
        }

        $canal->save();

        if ($request->has('habilitado')) {
            $canal->aplicarHabilitado((bool) $request->input('habilitado'));
            $campos[] = 'habilitado';
        }

        AuditoriaPublicacion::registrar('canal', 'edicion', array_values(array_unique($campos)));

        return redirect('admin/canales')
            ->with('mensaje', 'Canal actualizado: '.$canal->nombre);
    }

    public function estado(Request $request, $id)
    {
        $request->validate(['habilitado' => ['required', 'boolean']]);

        $canal = PublicacionCanal::findOrFail($id);
        $antes = $canal->vigente_desde->toDateTimeString();

        $canal->aplicarHabilitado((bool) $request->input('habilitado'));

        AuditoriaPublicacion::registrar(
            'canal',
            'estado',
            ['habilitado', 'vigente_desde'],
            ['vigente_desde' => $antes],
            ['vigente_desde' => $canal->vigente_desde->toDateTimeString()]
        );

        return redirect('admin/canales')
            ->with('mensaje', $canal->habilitado ? 'Canal habilitado.' : 'Canal deshabilitado.');
    }

    public function probar($id)
    {
        $canal = PublicacionCanal::findOrFail($id);
        $resultado = app(PruebaCanal::class)->probar($canal);

        return redirect('admin/canales')->with(
            $resultado['ok'] ? 'mensaje' : 'error',
            $resultado['mensaje']
        );
    }

    public function destroy($id)
    {
        $canal = PublicacionCanal::findOrFail($id);
        $nombre = $canal->nombre;
        $canal->delete();

        AuditoriaPublicacion::registrar('canal', 'borrado', ['nombre', 'deleted_at']);

        return redirect('admin/canales')
            ->with('mensaje', 'Canal eliminado: '.$nombre);
    }

    private function credencialesDelFormulario(string $tipo, Request $request): array
    {
        if ($tipo === 'discord') {
            return ['webhook_url' => trim((string) $request->input('webhook_url', ''))];
        }

        return [
            'token' => trim((string) $request->input('token', '')),
            'chat_id' => trim((string) $request->input('chat_id', '')),
        ];
    }

    private function parseFecha(string $valor): Carbon
    {
        foreach (['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s'] as $formato) {
            try {
                $fecha = Carbon::createFromFormat($formato, $valor, 'UTC');

                if ($fecha !== false) {
                    return $fecha;
                }
            } catch (\Exception $error) {
                // Se prueba el siguiente formato.
            }
        }

        return now();
    }
}
