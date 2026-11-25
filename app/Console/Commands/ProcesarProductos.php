<?php

namespace App\Console\Commands;

use App\Services\Publicacion\PublicacionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Disparador unico de la publicacion en redes (REQ-ENT-14, ENT-15, NFR-08,
 * NFR-09).
 *
 * El antiguo n8n esta muerto: este comando ya no envia a `autos`, delega en
 * PublicacionService. El lock de cache evita corridas concurrentes.
 */
class ProcesarProductos extends Command
{
    protected $signature = 'productos:procesar
        {--dry-run : Cuenta lo que se haria sin escribir ni llamar APIs}
        {--limit= : Tope total de envios de la corrida}';

    protected $description = 'Procesa la publicacion pendiente en los canales configurados';

    public function handle()
    {
        $limite = null;
        $opcionLimite = $this->option('limit');

        if ($opcionLimite !== null) {
            if (!is_numeric($opcionLimite) || (int) $opcionLimite < 1) {
                $this->error('El limite debe ser un entero mayor a cero.');

                return 1;
            }

            $limite = (int) $opcionLimite;
        }

        $lock = Cache::lock('publicaciones:corrida', (int) config('publicaciones.lock_ttl'));

        if (!$lock->get()) {
            Log::info('publicacion.corrida', ['motivo' => 'corrida_en_curso']);
            $this->info('Corrida en curso; sin cambios.');

            return 0;
        }

        try {
            $resultado = app(PublicacionService::class)->corrida([
                'dry_run' => (bool) $this->option('dry-run'),
                'limite' => $limite,
            ]);

            $this->imprimir($resultado, (bool) $this->option('dry-run'));

            return $resultado['exit'];
        } finally {
            $lock->release();
        }
    }

    private function imprimir(array $resultado, bool $dryRun): void
    {
        switch ($resultado['estado']) {
            case 'apagado':
                $this->info('Publicaciones apagadas; sin cambios.');
                break;

            case 'corte_invalido':
                $this->error('Falta una fecha de corte global valida; sin cambios.');
                break;

            case 'ajustes_no_disponibles':
                $this->error('No se pudieron leer los ajustes; sin cambios.');
                break;

            case 'sin_canales':
                $this->info('Sin canales operativos; sin cambios.');
                break;

            default:
                $this->info(sprintf(
                    '%s %d | %s %d | Fallidas %d | Inciertas %d',
                    $dryRun ? 'Sembraria' : 'Sembradas',
                    $resultado['sembradas'],
                    $dryRun ? 'Enviaria' : 'Enviadas',
                    $resultado['enviadas'],
                    $resultado['fallidas'],
                    $resultado['inciertas']
                ));
        }
    }
}
