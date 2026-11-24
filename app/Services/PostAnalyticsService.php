<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Captura de visitas y clicks de publicaciones (productos).
 *
 * Aditivo y fail-open: cualquier excepcion se loguea sin PII y la respuesta
 * publica no cambia. Con `analytics.enabled` en false no se lee contexto ni se
 * toca la base. El dia operativo sale de AnalyticsTimeService.
 */
class PostAnalyticsService
{
    /**
     * @var AnalyticsTimeService
     */
    protected $time;

    public function __construct(AnalyticsTimeService $time)
    {
        $this->time = $time;
    }

    /**
     * Registra una visita: pageview y, si corresponde, visitante unico del dia.
     *
     * @param  int|string  $productoId
     * @param  string  $source  web|api
     * @param  array|null  $context
     * @return void
     */
    public function recordVisit($productoId, $source = 'web', array $context = null)
    {
        if (! $this->enabled()) {
            return;
        }

        $context = $this->context($context);

        if (! $this->shouldCount($source, $context)) {
            return;
        }

        $date = null;

        try {
            $date = $this->time->today();
            $identity = $this->identity($context['cookie'], $context['ip']);
            $now = Carbon::now('UTC')->toDateTimeString();

            DB::transaction(function () use ($productoId, $date, $identity, $now) {
                $isNewIdentity = DB::affectingStatement(
                    'INSERT INTO post_visit_identities (producto_id, date, identity, created_at) '
                    .'VALUES (?, ?, ?, ?) '
                    .'ON DUPLICATE KEY UPDATE identity = identity',
                    [(int) $productoId, $date, $identity, $now]
                ) > 0;

                DB::affectingStatement(
                    'INSERT INTO post_stats_daily '
                    .'(producto_id, date, visits, unique_visits, clicks, created_at, updated_at) '
                    .'VALUES (?, ?, 1, ?, 0, ?, ?) '
                    .'ON DUPLICATE KEY UPDATE visits = visits + 1, '
                    .'unique_visits = unique_visits + VALUES(unique_visits), '
                    .'updated_at = VALUES(updated_at)',
                    [(int) $productoId, $date, $isNewIdentity ? 1 : 0, $now, $now]
                );
            });
        } catch (\Throwable $e) {
            $this->logFailure('visit', $productoId, $e, $date);
        }
    }

    /**
     * Registra un click saliente: evento inmutable y contador diario.
     *
     * Un `hotLink` vacio no cuenta: evita el bucle de redirect preexistente.
     * El evento guarda `date` local y `created_at` UTC.
     *
     * @param  int|string  $productoId
     * @param  string|null  $hotLink
     * @param  array|null  $context
     * @return void
     */
    public function recordClick($productoId, $hotLink, array $context = null)
    {
        if (! $this->enabled()) {
            return;
        }

        if (trim((string) $hotLink) === '') {
            return;
        }

        $context = $this->context($context);
        $source = $context['source'] ?: $this->sourceFromRequest();

        if (! $this->shouldCount($source, $context)) {
            return;
        }

        $date = null;

        try {
            $date = $this->time->today();
            $identity = $this->identity($context['cookie'], $context['ip']);
            $now = Carbon::now('UTC');
            $nowString = $now->toDateTimeString();
            $referer = $this->truncate($context['referer']);
            $userAgent = $this->truncate($context['user_agent']);
            $ipHash = $this->ipHash($context['ip']);

            DB::transaction(function () use (
                $productoId,
                $date,
                $identity,
                $source,
                $referer,
                $userAgent,
                $ipHash,
                $now,
                $nowString
            ) {
                DB::table('post_clicks')->insert([
                    'producto_id' => (int) $productoId,
                    'date' => $date,
                    'identity' => $identity,
                    'source' => $source,
                    'referer' => $referer,
                    'user_agent' => $userAgent,
                    'ip_hash' => $ipHash,
                    'created_at' => $now,
                ]);

                DB::affectingStatement(
                    'INSERT INTO post_stats_daily '
                    .'(producto_id, date, visits, unique_visits, clicks, created_at, updated_at) '
                    .'VALUES (?, ?, 0, NULL, 1, ?, ?) '
                    .'ON DUPLICATE KEY UPDATE clicks = clicks + 1, '
                    .'updated_at = VALUES(updated_at)',
                    [(int) $productoId, $date, $nowString, $nowString]
                );
            });
        } catch (\Throwable $e) {
            $this->logFailure('click', $productoId, $e, $date);
        }
    }

    /**
     * Identidad anonima: cookie valida o `ip:` + hash de la IP.
     *
     * @param  string|null  $cookie
     * @param  string|null  $ip
     * @return string
     */
    public function identity($cookie, $ip)
    {
        if (is_string($cookie) && preg_match('/^[A-Za-z0-9-]{8,64}$/', $cookie) === 1) {
            return strtolower($cookie);
        }

        return 'ip:'.$this->ipHash($ip);
    }

    /**
     * Hash estable de la IP (64 hex en minuscula).
     *
     * @param  string|null  $ip
     * @return string
     */
    public function ipHash($ip)
    {
        return hash('sha256', (string) $ip.'|'.(string) config('app.key'));
    }

    /**
     * Un UA vacio o que matchee la lista de bots no cuenta.
     *
     * @param  string|null  $userAgent
     * @return bool
     */
    public function isBot($userAgent)
    {
        $userAgent = (string) $userAgent;

        if (trim($userAgent) === '') {
            return true;
        }

        return preg_match(config('analytics.bot_ua_regex'), $userAgent) === 1;
    }

    /**
     * Prefetch y prerender del navegador no cuentan.
     *
     * @param  string|null  $purpose
     * @return bool
     */
    public function isPrefetch($purpose)
    {
        $purpose = strtolower((string) $purpose);

        if ($purpose === '') {
            return false;
        }

        return strpos($purpose, 'prefetch') !== false
            || strpos($purpose, 'prerender') !== false;
    }

    /**
     * @return bool
     */
    protected function enabled()
    {
        return (bool) config('analytics.enabled', false);
    }

    /**
     * Completa el contexto explicito o lo resuelve del request actual.
     *
     * @param  array|null  $context
     * @return array
     */
    protected function context(array $context = null)
    {
        $context = $context ?: [];

        if ($context) {
            return array_merge([
                'cookie' => null,
                'ip' => null,
                'user_agent' => null,
                'referer' => null,
                'purpose' => null,
                'source' => null,
            ], $context);
        }

        $request = request();

        if (! $request) {
            return [
                'cookie' => null,
                'ip' => null,
                'user_agent' => null,
                'referer' => null,
                'purpose' => null,
                'source' => null,
            ];
        }

        return [
            'cookie' => $request->cookie('anonymous_user_id'),
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'referer' => $request->headers->get('referer'),
            'purpose' => $request->header('Purpose') ?: $request->header('Sec-Purpose'),
            'source' => null,
        ];
    }

    /**
     * Borra eventos crudos mas viejos que la retencion, en lotes de 1000 y en
     * UTC. No toca `post_stats_daily`.
     *
     * @param  int|string|null  $days
     * @return array
     */
    public function purgeEvents($days = null)
    {
        $days = (int) ($days === null || $days === ''
            ? config('analytics.retention_days', 90)
            : $days);
        $days = $days < 1 ? 1 : $days;

        $cutoff = Carbon::now('UTC')->subDays($days);
        $deleted = [];

        foreach (['post_clicks', 'post_visit_identities'] as $table) {
            $total = 0;

            do {
                $count = DB::table($table)
                    ->where('created_at', '<', $cutoff)
                    ->limit(1000)
                    ->delete();
                $total += $count;
            } while ($count > 0);

            $deleted[$table] = $total;
        }

        return $deleted;
    }

    /**
     * Purga oportunista: como maximo una vez cada 24 h. Cualquier falla se
     * loguea y nunca se propaga.
     *
     * @return int
     */
    public function purgeIfDue()
    {
        if (! config('analytics.opportunistic_purge', false)) {
            return 0;
        }

        try {
            if (Cache::get('analytics:purge:last') !== null) {
                return 0;
            }

            $deleted = array_sum($this->purgeEvents());

            Cache::put(
                'analytics:purge:last',
                Carbon::now('UTC')->toDateTimeString(),
                Carbon::now('UTC')->addDay()
            );

            return $deleted;
        } catch (\Throwable $e) {
            Log::error('analytics:purge_failed', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    /**
     * Origen del click: el prefijo `api/` del path o `web` por defecto.
     *
     * @return string
     */
    protected function sourceFromRequest()
    {
        $request = request();

        if (! $request) {
            return 'web';
        }

        return strpos(ltrim($request->path(), '/'), 'api/') === 0 ? 'api' : 'web';
    }

    /**
     * Trunca referer y UA a 255; vacio se guarda como null.
     *
     * @param  string|null  $value
     * @return string|null
     */
    protected function truncate($value)
    {
        $value = (string) $value;

        return $value === '' ? null : substr($value, 0, 255);
    }

    /**
     * Aplica los filtros de §1.3 antes de escribir.
     *
     * @param  string  $source
     * @param  array  $context
     * @return bool
     */
    protected function shouldCount($source, array $context)
    {
        if ($this->isBot($context['user_agent'])) {
            return false;
        }

        if ($this->isPrefetch($context['purpose'])) {
            return false;
        }

        if ($source === 'web' && session()->has('admin')) {
            return false;
        }

        return true;
    }

    /**
     * Log de falla sin PII (NFR-03, NFR-04).
     *
     * @param  string  $type
     * @param  int|string  $productoId
     * @param  \Throwable  $e
     * @param  string|null  $date
     * @return void
     */
    protected function logFailure($type, $productoId, \Throwable $e, $date = null)
    {
        Log::error('analytics:capture_failed', [
            'type' => $type,
            'producto_id' => (int) $productoId,
            'date' => $date,
            'error' => $e->getMessage(),
        ]);
    }
}
