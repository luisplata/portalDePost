<?php

/*
|--------------------------------------------------------------------------
| Analitica de publicaciones
|--------------------------------------------------------------------------
|
| Valores centralizados de la analitica de publicaciones (productos). Todo el
| codigo nuevo lee de aca; nunca env() fuera de config/. Cada valor tiene un
| default explicito porque el deploy no activa config:cache.
|
| La captura arranca apagada: el sitio publico se comporta igual hasta que se
| encienda la perilla.
|
*/

return [

    // Habilitador de la captura (visitas y clicks). La lectura del panel no se
    // apaga con esta perilla.
    'enabled' => env('ANALYTICS_ENABLED', false),

    // Zona horaria del dia operativo. `config('app.timezone')` sigue en UTC:
    // el crudo se guarda en UTC y solo el dia de negocio usa esta zona.
    'timezone' => env('ANALYTICS_TIMEZONE', 'America/Bogota'),

    // Retencion de eventos de click e identidades de visita (dias).
    'retention_days' => 90,

    // Ventana por default del backfill de visitas historicas (dias).
    'backfill_days' => 365,

    // User-Agents que no cuentan ni visita ni click. Mismo patron que ads.
    'bot_ua_regex' => '/bot|crawl|spider|slurp|bingpreview|headless|python|curl|wget|go-http|okhttp/i',

    // Umbrales del chequeo de drift contra log_visits.
    'drift_threshold_pct' => 5,
    'drift_min_volume' => 50,

    // Purga oportunista al abrir el panel. El cron diario es la via principal.
    'opportunistic_purge' => false,

];
