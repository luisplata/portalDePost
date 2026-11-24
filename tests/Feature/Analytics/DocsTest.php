<?php

namespace Tests\Feature\Analytics;

use Tests\TestCase;

/**
 * Documento de contrato de analitica (T-S4b-04, NFR-06).
 */
class DocsTest extends TestCase
{
    public function test_the_analytics_doc_exists_and_covers_the_contract()
    {
        $path = base_path('docs/analitica-publicaciones.md');

        $this->assertFileExists($path);

        $doc = file_get_contents($path);

        foreach ([
            'Conversion principal',
            'uv_approximate',
            'ANALYTICS_ENABLED',
            'analytics:backfill-visits',
            'analytics:purge-events',
            'analytics:check-drift',
            'admin/analitica',
            'vendor/bin/phpunit',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }
    }
}
