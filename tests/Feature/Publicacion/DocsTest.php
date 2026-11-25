<?php

namespace Tests\Feature\Publicacion;

use Tests\TestCase;

/**
 * Contrato documental (T-S7b-06, REQ-NFR-06, SEG-06).
 */
class DocsTest extends TestCase
{
    public function test_docs_cover_effective_cutoff_fail_closed_and_commands()
    {
        $ruta = base_path('docs/publicacion-redes.md');

        $this->assertFileExists($ruta);

        $contenido = preg_replace('/\s+/', ' ', file_get_contents($ruta));

        foreach ([
            'Canales',
            'APP_KEY',
            'interruptor',
            'corte efectivo',
            'vigente_desde',
            'estados de entrega',
            'backoff',
            '429',
            'productos:procesar',
            '--dry-run',
            'publicaciones:probar',
            'agregar una red nueva',
            'driver',
            'incierto',
            'timeout',
            'sin worker',
            'sin colas',
        ] as $termino) {
            $this->assertStringContainsStringIgnoringCase($termino, $contenido, $termino);
        }
    }
}
