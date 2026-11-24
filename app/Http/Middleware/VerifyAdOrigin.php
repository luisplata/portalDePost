<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Validaciones anti-abuso del beacon de impresion (REQ-IMP-03).
 *
 * - Metodo POST.
 * - Misma-origen: host de Origin o, en su defecto, de Referer contra el host
 *   de la app. Sin Origin ni Referer => 403.
 * - User-Agent no vacio y que no matchee config('ads.bot_ua_regex') => 403.
 *
 * Nunca responde por existencia de creatividad: eso es 204 silencioso en el
 * controlador (D-04).
 */
class VerifyAdOrigin
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (! $request->isMethod('POST')) {
            return response('', 405);
        }

        $origin = (string) $request->headers->get('Origin');
        $referer = (string) $request->headers->get('Referer');
        $host = $request->getHost();

        if ($origin !== '') {
            $sourceHost = parse_url($origin, PHP_URL_HOST);

            if (! is_string($sourceHost) || strcasecmp($sourceHost, $host) !== 0) {
                return response()->json(['error' => 'forbidden'], 403);
            }
        } elseif ($referer !== '') {
            $sourceHost = parse_url($referer, PHP_URL_HOST);

            if (! is_string($sourceHost) || strcasecmp($sourceHost, $host) !== 0) {
                return response()->json(['error' => 'forbidden'], 403);
            }
        } else {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $userAgent = (string) $request->userAgent();

        if (trim($userAgent) === '') {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $botPattern = (string) config(
            'ads.bot_ua_regex',
            '/bot|crawl|spider|slurp|bingpreview|headless|python|curl|wget|go-http|okhttp/i'
        );

        if (preg_match($botPattern, $userAgent) === 1) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        return $next($request);
    }
}
