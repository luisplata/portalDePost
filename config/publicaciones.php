<?php

/**
 * Configuracion de la publicacion en redes (REQ-NFR-01, NFR-02, NFR-04, NFR-06).
 *
 * Todo `env()` de este dominio vive aca. El interruptor y el corte en caliente
 * NO se leen de este archivo: viven en publicacion_ajustes (REQ-AJUS-01). Estas
 * dos claves solo siembran la base la primera vez.
 */
return [
    'drivers' => [
        'telegram' => App\Services\Publicacion\TelegramDriver::class,
        'discord' => App\Services\Publicacion\DiscordDriver::class,
    ],

    'etiquetas' => [
        'telegram' => 'TG',
        'discord' => 'DC',
    ],

    'lote_max' => 10,
    'ventana_sembrado_dias' => 7,

    'connect_timeout' => 5,
    'read_timeout' => 15,
    'pausa_ms' => 800,

    'intentos_max' => 5,
    'backoff_minutos' => [1, 5, 15, 60],

    'espera_min' => 1,
    'espera_max' => 900,
    'espera_default' => 60,

    'lock_ttl' => 1800,

    'sembrado_enabled' => filter_var(env('PUBLICACIONES_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'sembrado_desde' => env('PUBLICACIONES_DESDE'),
];
