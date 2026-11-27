<?php

/*
|--------------------------------------------------------------------------
| Publicidad (ads)
|--------------------------------------------------------------------------
|
| Valores centralizados del sistema de publicidad. Todo el codigo nuevo,
| las vistas y las migraciones leen de aca; nunca env() fuera de config/.
| Cada valor tiene un default explicito porque el deploy no activa
| config:cache (H-2/H-3 del design).
|
*/

return [

    // TTL (segundos) de la seleccion cacheada por slot: ads:slot:{key}.
    'cache_ttl' => 300,

    // Requests por minuto y por IP permitidos en el tracking (impresiones y clicks).
    'tracking_rate_limit' => 120,

    // Retencion del detalle de clicks (ad_clicks). Los agregados no se purgan.
    'retention_days' => 90,

    // Zona horaria del bucket diario de metricas (ad_stats_daily.date).
    // El almacenamiento crudo (created_at) sigue en UTC (D-11 / E-1).
    'timezone' => env('ADS_TIMEZONE', 'America/Bogota'),

    // Keys legacy de /publicity/{key} => campaign_key de la creatividad que la reemplaza.
    'legacy_aliases' => [
        'game' => 'game-home_sidebar',
    ],

    // Placements contractuales (REQ-PLC-01). max_items se edita desde el admin.
    'placements' => [
        'home_sidebar' => [
            'name' => 'Home sidebar',
            'max_items' => 1,
        ],
        'product_sidebar' => [
            'name' => 'Ficha de producto sidebar',
            'max_items' => 1,
        ],
        'ppv_sidebar' => [
            'name' => 'PPV sidebar',
            'max_items' => 1,
        ],
        'product_banner_grid' => [
            'name' => 'Grilla de banners de ficha',
            'max_items' => 6,
        ],
    ],

    // User-Agents que no cuentan como impresion (REQ-IMP-03).
    'bot_ua_regex' => '/bot|crawl|spider|slurp|bingpreview|headless|python|curl|wget|go-http|okhttp/i',

];
