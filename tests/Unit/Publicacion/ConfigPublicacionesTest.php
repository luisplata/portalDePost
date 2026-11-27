<?php

namespace Tests\Unit\Publicacion;

use Tests\TestCase;

/**
 * Defaults contractuales de config/publicaciones.php (T-S1-07, NFR-01, NFR-02,
 * NFR-04, NFR-06).
 */
class ConfigPublicacionesTest extends TestCase
{
    public function test_defaults_match_the_contract()
    {
        $this->assertSame(
            ['telegram', 'discord'],
            array_keys(config('publicaciones.drivers'))
        );
        $this->assertSame(
            'App\Services\Publicacion\TelegramDriver',
            config('publicaciones.drivers.telegram')
        );
        $this->assertSame(
            'App\Services\Publicacion\DiscordDriver',
            config('publicaciones.drivers.discord')
        );
        $this->assertSame(['telegram' => 'TG', 'discord' => 'DC'], config('publicaciones.etiquetas'));
        $this->assertSame(10, config('publicaciones.lote_max'));
        $this->assertSame(7, config('publicaciones.ventana_sembrado_dias'));
        $this->assertSame(5, config('publicaciones.connect_timeout'));
        $this->assertSame(15, config('publicaciones.read_timeout'));
        $this->assertSame(800, config('publicaciones.pausa_ms'));
        $this->assertSame(5, config('publicaciones.intentos_max'));
        $this->assertSame([1, 5, 15, 60], config('publicaciones.backoff_minutos'));
        $this->assertSame(1, config('publicaciones.espera_min'));
        $this->assertSame(900, config('publicaciones.espera_max'));
        $this->assertSame(60, config('publicaciones.espera_default'));
        $this->assertSame(1800, config('publicaciones.lock_ttl'));
    }

    public function test_the_seed_flag_is_off_by_default_and_reads_only_from_env_here()
    {
        $this->assertFalse(config('publicaciones.sembrado_enabled'));
        $this->assertNull(config('publicaciones.sembrado_desde'));
    }
}
