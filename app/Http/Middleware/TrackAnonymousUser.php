<?php

namespace App\Http\Middleware;

use App\ActivitiesAnonymousUsers;
use App\AnonymousUsers;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TrackAnonymousUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */

    public function handle($request, Closure $next)
    {
        $nameOfCookie = 'anonymous_user_id';
        $anonymousUserId = Cookie::get($nameOfCookie);

        $botUserAgent = '_Bot';
        $agent = $request->header('User-Agent');
        if (str_contains($agent, $botUserAgent)) {
            $anonymousUserId = $agent;
        }

        if (!$anonymousUserId) {
            $anonymousUserId = (string) Str::uuid();;
            Cookie::queue($nameOfCookie, $anonymousUserId, 60 * 24 * 365);
        }
        if ($anonymousUserId && !AnonymousUsers::where('uuid_user', $anonymousUserId)->exists()) {
            AnonymousUsers::create(['uuid_user' => $anonymousUserId]);
        }

        ActivitiesAnonymousUsers::create([
            'user_uuid' => $anonymousUserId,
            'page' => $request->path(),
            'data' => json_encode([
                'referer' => $request->header('referer'),
                'ip' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'url' => $request->fullUrl(),
            ]),
        ]);

        return $next($request);
    }
}
