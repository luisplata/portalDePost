<?php

namespace App\Services\Publicacion;

use App\Producto;
use App\PublicacionAjuste;
use App\PublicacionCanal;
use App\PublicacionEnvio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orquestador de la publicacion en redes (REQ-ENT-01..13, AJUS-01..06).
 *
 * Sembrado idempotente por canal, envio con lote y pausa, transiciones con
 * backoff, 429 sin consumir intento, incierto sin reintento automatico y
 * recalculo de `published` solo sobre los productos tocados.
 */
class PublicacionService
{
    /** @var Espera */
    private $espera;

    public function __construct(Espera $espera)
    {
        $this->espera = $espera;
    }

    /**
     * @param  array{dry_run?: bool, limite?: int|null}  $opciones
     * @return array{estado: string, motivo: string|null, exit: int, sembradas: int, enviadas: int, fallidas: int, inciertas: int, duracion_ms: int}
     */
    public function corrida(array $opciones = []): array
    {
        $inicio = microtime(true);
        $dryRun = !empty($opciones['dry_run']);
        $limite = array_key_exists('limite', $opciones) ? $opciones['limite'] : null;

        try {
            $ajustes = $this->leerAjustes();
        } catch (\Throwable $error) {
            Log::error('publicacion.corrida', ['motivo' => 'ajustes_no_disponibles']);

            return $this->resultado('ajustes_no_disponibles', 1, $inicio, 'ajustes_no_disponibles');
        }

        if (($ajustes[PublicacionAjuste::CLAVE_ACTIVO] ?? null) !== '1') {
            Log::info('publicacion.corrida', ['motivo' => 'apagado']);

            return $this->resultado('apagado', 0, $inicio, 'apagado');
        }

        $desde = $this->parseCorte($ajustes[PublicacionAjuste::CLAVE_DESDE] ?? null);

        if ($desde === null) {
            Log::error('publicacion.corrida', ['motivo' => 'corte_invalido']);

            return $this->resultado('corte_invalido', 1, $inicio, 'corte_invalido');
        }

        $canales = PublicacionCanal::operativos()->orderBy('id')->get();

        if ($canales->isEmpty()) {
            Log::info('publicacion.corrida', ['motivo' => 'sin_canales']);

            if (!$dryRun) {
                PublicacionAjuste::registrarCorrida($this->resumenCorrida($inicio, [
                    'sembradas' => 0,
                    'enviadas' => 0,
                    'fallidas' => 0,
                    'inciertas' => 0,
                ]));
            }

            return $this->resultado('sin_canales', 0, $inicio, 'sin_canales');
        }

        $resumen = ['sembradas' => 0, 'enviadas' => 0, 'fallidas' => 0, 'inciertas' => 0];
        $tocados = [];

        foreach ($canales as $canal) {
            if (!$this->tieneDriver($canal)) {
                $this->marcarTipoDesconocido($canal);
                continue;
            }

            $ids = $this->sembrarCanal($canal, $desde, $dryRun);
            $resumen['sembradas'] += count($ids);
            $tocados = array_merge($tocados, $ids);
        }

        $tocados = array_merge(
            $tocados,
            $this->enviarPendientes($canales, $dryRun, $limite, $resumen)
        );

        if (!$dryRun) {
            $this->recalcularPublished($desde, $tocados);
            PublicacionAjuste::registrarCorrida($this->resumenCorrida($inicio, $resumen));
        }

        Log::info('publicacion.corrida', [
            'motivo' => 'ok',
            'sembradas' => $resumen['sembradas'],
            'enviadas' => $resumen['enviadas'],
            'fallidas' => $resumen['fallidas'],
            'inciertas' => $resumen['inciertas'],
        ]);

        return $this->resultado('ok', 0, $inicio, null, $resumen);
    }

    /**
     * Reintento manual inline desde fallido, incierto o enviado (REQ-ENT-09).
     *
     * @return array{ok: bool, mensaje: string, resultado?: ResultadoEntrega}
     */
    public function reintentar(PublicacionEnvio $envio): array
    {
        if ($envio->estado === PublicacionEnvio::PENDIENTE) {
            return ['ok' => false, 'mensaje' => 'La entrega ya esta pendiente.'];
        }

        $canal = $envio->canal;

        if (!$canal
            || !$canal->habilitado
            || $canal->estado !== 'ok'
            || $canal->deleted_at !== null
        ) {
            return ['ok' => false, 'mensaje' => 'El canal no esta operativo.'];
        }

        $driver = $this->driverDe($canal->tipo);

        if ($driver === null) {
            return ['ok' => false, 'mensaje' => 'El tipo de canal no tiene driver.'];
        }

        $credenciales = Secretos::descifrar($canal);

        if ($credenciales === null) {
            $this->marcarCredencialInvalida($canal);

            return ['ok' => false, 'mensaje' => 'Vuelva a cargar las credenciales.'];
        }

        $producto = $envio->producto;

        if ($producto === null) {
            return ['ok' => false, 'mensaje' => 'El producto ya no existe.'];
        }

        $resultado = $driver->enviar($producto, $credenciales, ProductoMensaje::link($producto));
        $destino = $this->aplicarTransicion($envio, $resultado, $canal);

        $desde = $this->parseCorte(
            PublicacionAjuste::query()
                ->where('clave', PublicacionAjuste::CLAVE_DESDE)
                ->value('valor')
        );

        if ($desde !== null) {
            $this->recalcularPublished($desde, [$producto->id]);
        }

        return [
            'ok' => true,
            'mensaje' => $destino === 'enviada'
                ? 'Entrega enviada.'
                : 'Reintento registrado: '.$envio->fresh()->estado.'.',
            'resultado' => $resultado,
        ];
    }

    private function leerAjustes(): array
    {
        return PublicacionAjuste::query()
            ->whereIn('clave', [
                PublicacionAjuste::CLAVE_ACTIVO,
                PublicacionAjuste::CLAVE_DESDE,
            ])
            ->pluck('valor', 'clave')
            ->all();
    }

    private function parseCorte($valor)
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d\TH:i'] as $formato) {
            try {
                $fecha = Carbon::createFromFormat($formato, $valor, 'UTC');

                if ($fecha !== false) {
                    return $fecha;
                }
            } catch (\Exception $error) {
                // Se prueba el siguiente formato.
            }
        }

        return null;
    }

    /**
     * @return int[] ids sembrados en este canal
     */
    private function sembrarCanal(PublicacionCanal $canal, Carbon $desde, bool $dryRun): array
    {
        $corte = $canal->vigente_desde->greaterThan($desde) ? $canal->vigente_desde : $desde;

        $tieneEntregas = DB::table('publicacion_envios')
            ->where('publicacion_canal_id', $canal->id)
            ->exists();

        if (!$tieneEntregas) {
            $ventana = now()->subDays((int) config('publicaciones.ventana_sembrado_dias'));

            if ($ventana->greaterThan($corte)) {
                $corte = $ventana;
            }
        }

        if (now()->lessThan($corte)) {
            return [];
        }

        $ahora = now()->format('Y-m-d H:i:s');
        $corteTexto = $corte->format('Y-m-d H:i:s');

        $candidatos = DB::select(
            'SELECT p.id FROM productos p'
            .' LEFT JOIN publicacion_envios e ON e.producto_id = p.id'
            .' AND e.publicacion_canal_id = ?'
            .' WHERE e.id IS NULL AND p.estado = 1'
            .' AND p.publication_date <= ? AND p.created_at >= ?',
            [$canal->id, $ahora, $corteTexto]
        );

        if ($candidatos === [] || $dryRun) {
            return array_map(function ($fila) {
                return (int) $fila->id;
            }, $candidatos);
        }

        DB::affectingStatement(
            'INSERT INTO publicacion_envios'
            .' (producto_id, publicacion_canal_id, canal_tipo, estado, intentos, created_at, updated_at)'
            .' SELECT p.id, ?, ?, ?, 0, ?, ? FROM productos p'
            .' LEFT JOIN publicacion_envios e ON e.producto_id = p.id'
            .' AND e.publicacion_canal_id = ?'
            .' WHERE e.id IS NULL AND p.estado = 1'
            .' AND p.publication_date <= ? AND p.created_at >= ?',
            [
                $canal->id,
                $canal->tipo,
                PublicacionEnvio::PENDIENTE,
                $ahora,
                $ahora,
                $canal->id,
                $ahora,
                $corteTexto,
            ]
        );

        return array_map(function ($fila) {
            return (int) $fila->id;
        }, $candidatos);
    }

    /**
     * @param  \Illuminate\Support\Collection  $canales
     * @return int[] ids de productos intentados
     */
    private function enviarPendientes($canales, bool $dryRun, $limite, array &$resumen): array
    {
        $tocados = [];
        $despachados = 0;
        $programados = $this->programados($canales, $limite);

        foreach ($canales as $canal) {
            if (!$this->tieneDriver($canal)) {
                continue;
            }

            $credenciales = Secretos::descifrar($canal);

            if ($credenciales === null) {
                $this->marcarCredencialInvalida($canal);
                continue;
            }

            $driver = $this->driverDe($canal->tipo);
            $lote = (int) config('publicaciones.lote_max');

            if ($limite !== null) {
                $lote = min($lote, max(0, (int) $limite - $despachados));
            }

            $pendientes = $this->pendientesDe($canal, $lote);

            if ($dryRun) {
                $despachados += count($pendientes);
                $resumen['enviadas'] += count($pendientes);
                continue;
            }

            foreach ($pendientes as $envio) {
                $producto = Producto::find($envio->producto_id);

                if ($producto === null) {
                    continue;
                }

                $resultado = $driver->enviar(
                    $producto,
                    $credenciales,
                    ProductoMensaje::link($producto)
                );

                $destino = $this->aplicarTransicion($envio, $resultado, $canal);
                $tocados[] = $producto->id;
                $despachados++;

                if ($destino === 'enviada') {
                    $resumen['enviadas']++;
                } elseif ($destino === 'fallida') {
                    $resumen['fallidas']++;
                } elseif ($destino === 'incierta') {
                    $resumen['inciertas']++;
                }

                Log::info('publicacion.entrega', [
                    'producto_id' => $producto->id,
                    'canal_id' => $canal->id,
                    'tipo' => $canal->tipo,
                    'estado' => $envio->estado,
                    'http' => $resultado->httpStatus,
                ]);

                if ($canal->estado !== 'ok') {
                    break;
                }

                if ($despachados < $programados) {
                    $this->espera->ms((int) config('publicaciones.pausa_ms'));
                }
            }
        }

        return $tocados;
    }

    /**
     * Envios que esta corrida pretende procesar, para espaciar solo entre ellos.
     */
    private function programados($canales, $limite): int
    {
        $ids = $canales->pluck('id')->all();

        if ($ids === []) {
            return 0;
        }

        $filas = DB::select(
            'SELECT e.publicacion_canal_id AS canal_id, COUNT(*) AS total'
            .' FROM publicacion_envios e JOIN productos p ON p.id = e.producto_id'
            .' WHERE e.estado = ? AND (e.proximo_intento_at IS NULL OR e.proximo_intento_at <= ?)'
            .' AND e.publicacion_canal_id IN ('.implode(',', array_fill(0, count($ids), '?')).')'
            .' GROUP BY e.publicacion_canal_id',
            array_merge([PublicacionEnvio::PENDIENTE, now()->format('Y-m-d H:i:s')], $ids)
        );

        $lote = (int) config('publicaciones.lote_max');
        $total = 0;

        foreach ($filas as $fila) {
            $total += min($lote, (int) $fila->total);
        }

        return $limite === null ? $total : min($total, max(0, (int) $limite));
    }

    /**
     * @return \Illuminate\Support\Collection<int, PublicacionEnvio>
     */
    private function pendientesDe(PublicacionCanal $canal, int $lote)
    {
        if ($lote <= 0) {
            return collect();
        }

        $ids = DB::select(
            'SELECT e.id FROM publicacion_envios e JOIN productos p ON p.id = e.producto_id'
            .' WHERE e.publicacion_canal_id = ? AND e.estado = ?'
            .' AND (e.proximo_intento_at IS NULL OR e.proximo_intento_at <= ?)'
            .' ORDER BY p.publication_date ASC, e.id ASC LIMIT '.$lote,
            [$canal->id, PublicacionEnvio::PENDIENTE, now()->format('Y-m-d H:i:s')]
        );

        return collect($ids)->map(function ($fila) {
            return PublicacionEnvio::find($fila->id);
        })->filter();
    }

    /**
     * @return string 'enviada' | 'fallida' | 'incierta' | 'pendiente'
     */
    private function aplicarTransicion(
        PublicacionEnvio $envio,
        ResultadoEntrega $resultado,
        PublicacionCanal $canal
    ): string {
        $ahora = now();

        switch ($resultado->resultado) {
            case ResultadoEntrega::ENTREGADO:
                $envio->estado = PublicacionEnvio::ENVIADO;
                $envio->enviado_at = $ahora;
                $envio->referencia_externa = $resultado->referencia;
                $envio->http_status = $resultado->httpStatus;
                $envio->error = null;
                $envio->proximo_intento_at = null;
                $envio->save();

                return 'enviada';

            case ResultadoEntrega::TEMPORAL:
                $espera = $this->clampEspera($resultado->espera);
                $envio->estado = PublicacionEnvio::PENDIENTE;
                $envio->proximo_intento_at = $ahora->copy()->addSeconds($espera);
                $envio->http_status = $resultado->httpStatus;
                $envio->error = $resultado->error;
                $envio->save();

                return 'pendiente';

            case ResultadoEntrega::PERMANENTE:
                $envio->estado = PublicacionEnvio::FALLIDO;
                $envio->intentos = $envio->intentos + 1;
                $envio->proximo_intento_at = null;
                $envio->http_status = $resultado->httpStatus;
                $envio->error = $resultado->error;
                $envio->save();

                if ($resultado->invalidaCredencial) {
                    $this->marcarCredencialInvalida($canal);
                }

                return 'fallida';

            case ResultadoEntrega::AMBIGUO:
                $envio->estado = PublicacionEnvio::INCIERTO;
                $envio->intentos = $envio->intentos + 1;
                $envio->proximo_intento_at = null;
                $envio->http_status = $resultado->httpStatus;
                $envio->error = $resultado->error;
                $envio->save();

                return 'incierta';

            case ResultadoEntrega::SERVIDOR:
            default:
                $intentosPrevios = $envio->intentos;
                $envio->intentos = $intentosPrevios + 1;
                $envio->http_status = $resultado->httpStatus;
                $envio->error = $resultado->error;

                if ($envio->intentos >= (int) config('publicaciones.intentos_max')) {
                    $envio->estado = PublicacionEnvio::FALLIDO;
                    $envio->proximo_intento_at = null;
                    $envio->save();

                    return 'fallida';
                }

                $backoff = config('publicaciones.backoff_minutos');
                $minutos = isset($backoff[$intentosPrevios])
                    ? (int) $backoff[$intentosPrevios]
                    : (int) end($backoff);

                $envio->estado = PublicacionEnvio::PENDIENTE;
                $envio->proximo_intento_at = $ahora->copy()->addMinutes($minutos);
                $envio->save();

                return 'pendiente';
        }
    }

    private function clampEspera($espera): int
    {
        $espera = $espera === null
            ? (int) config('publicaciones.espera_default')
            : (int) $espera;

        return max(
            (int) config('publicaciones.espera_min'),
            min((int) config('publicaciones.espera_max'), $espera)
        );
    }

    /**
     * Deja published en 1 solo cuando todos los canales aplicables enviaron.
     *
     * @param  int[]  $ids
     */
    private function recalcularPublished(Carbon $desde, array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        foreach (array_chunk($ids, 500) as $chunk) {
            $filas = DB::select(
                'SELECT p.id, p.published,'
                .' SUM(CASE WHEN c.id IS NOT NULL THEN 1 ELSE 0 END) AS aplicables,'
                ." SUM(CASE WHEN e.estado = 'enviado' THEN 1 ELSE 0 END) AS enviados"
                .' FROM productos p'
                .' LEFT JOIN publicacion_canales c ON c.habilitado = 1 AND c.estado = \'ok\''
                .' AND c.deleted_at IS NULL AND c.vigente_desde <= p.created_at'
                .' LEFT JOIN publicacion_envios e ON e.producto_id = p.id'
                .' AND e.publicacion_canal_id = c.id'
                .' WHERE p.id IN ('.implode(',', array_fill(0, count($chunk), '?')).')'
                .' AND p.created_at >= ?'
                .' GROUP BY p.id, p.published',
                array_merge($chunk, [$desde->format('Y-m-d H:i:s')])
            );

            $activar = [];
            $desactivar = [];

            foreach ($filas as $fila) {
                $aplicables = (int) $fila->aplicables;

                if ($aplicables === 0) {
                    continue;
                }

                $objetivo = ((int) $fila->enviados === $aplicables) ? 1 : 0;

                if ((int) $fila->published === $objetivo) {
                    continue;
                }

                if ($objetivo === 1) {
                    $activar[] = (int) $fila->id;
                } else {
                    $desactivar[] = (int) $fila->id;
                }
            }

            if ($activar !== []) {
                DB::table('productos')->whereIn('id', $activar)->update(['published' => 1]);
            }

            if ($desactivar !== []) {
                DB::table('productos')->whereIn('id', $desactivar)->update(['published' => 0]);
            }
        }
    }

    private function resumenCorrida(float $inicio, array $resumen): array
    {
        return [
            'corrida_at' => now(),
            'corrida_sembradas' => $resumen['sembradas'],
            'corrida_enviadas' => $resumen['enviadas'],
            'corrida_fallidas' => $resumen['fallidas'],
            'corrida_inciertas' => $resumen['inciertas'],
            'corrida_duracion_ms' => (int) round((microtime(true) - $inicio) * 1000),
        ];
    }

    private function resultado(
        string $estado,
        int $exit,
        float $inicio,
        $motivo,
        array $resumen = []
    ): array {
        return array_merge([
            'estado' => $estado,
            'motivo' => $motivo,
            'exit' => $exit,
            'sembradas' => 0,
            'enviadas' => 0,
            'fallidas' => 0,
            'inciertas' => 0,
            'duracion_ms' => (int) round((microtime(true) - $inicio) * 1000),
        ], $resumen);
    }

    private function tieneDriver(PublicacionCanal $canal): bool
    {
        return $this->driverDe($canal->tipo) !== null;
    }

    /**
     * @return CanalDriver|null
     */
    private function driverDe(string $tipo)
    {
        $clase = config('publicaciones.drivers.'.$tipo);

        if (!is_string($clase) || !class_exists($clase)) {
            return null;
        }

        return $this->app($clase);
    }

    private function app(string $clase)
    {
        return app($clase);
    }

    private function marcarCredencialInvalida(PublicacionCanal $canal): void
    {
        $canal->estado = 'invalido_credencial';
        $canal->estado_detalle = 'Vuelva a cargar las credenciales.';
        $canal->save();
    }

    private function marcarTipoDesconocido(PublicacionCanal $canal): void
    {
        $canal->estado = 'tipo_desconocido';
        $canal->estado_detalle = 'Driver no disponible.';
        $canal->save();
    }
}
