<?php

namespace App\Console\Commands;

use App\PublicacionCanal;
use App\Services\Publicacion\PruebaCanal;
use Illuminate\Console\Command;

/**
 * Envio de prueba de un canal por id o nombre (REQ-CAN-11).
 */
class ProbarPublicacion extends Command
{
    protected $signature = 'publicaciones:probar {--canal= : Id o nombre del canal}';
    protected $description = 'Envia un texto fijo para verificar un canal, sin crear entregas';

    public function handle()
    {
        $referencia = $this->option('canal');

        if ($referencia === null || trim((string) $referencia) === '') {
            $this->error('Indique --canal con el id o el nombre.');

            return 1;
        }

        $referencia = trim((string) $referencia);

        $canal = is_numeric($referencia)
            ? PublicacionCanal::find((int) $referencia)
            : PublicacionCanal::where('nombre', $referencia)->first();

        if ($canal === null) {
            $this->error('No existe un canal activo con esa referencia.');

            return 1;
        }

        $resultado = app(PruebaCanal::class)->probar($canal);

        if ($resultado['ok']) {
            $this->info($resultado['mensaje']);

            return 0;
        }

        $this->error($resultado['mensaje']);

        return 1;
    }
}
