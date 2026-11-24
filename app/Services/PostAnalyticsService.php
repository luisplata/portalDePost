<?php

namespace App\Services;

use Illuminate\Support\Carbon;
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
            ];
        }

        return [
            'cookie' => $request->cookie('anonymous_user_id'),
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'referer' => $request->headers->get('referer'),
            'purpose' => $request->header('Purpose') ?: $request->header('Sec-Purpose'),
        ];
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
