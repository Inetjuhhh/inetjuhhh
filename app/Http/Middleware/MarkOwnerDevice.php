<?php

namespace App\Http\Middleware;

use App\Support\ViewCounter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * A device Ine has used the admin on keeps a cookie for five years, so her own reading on it never
 * counts, also after logging out (see ViewCounter). It holds nothing but "don't count".
 */
class MarkOwnerDevice
{
    private const MINUTES = 60 * 24 * 365 * 5;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->cookie(ViewCounter::OWNER_COOKIE) === null) {
            Cookie::queue(ViewCounter::OWNER_COOKIE, '1', self::MINUTES);
        }

        return $next($request);
    }
}
