<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * In production the site has one address, the one in APP_URL. Visitors on another name for the same
 * server (www.inetjuhhh.nl, or the temporary nieuw.inetjuhhh.nl once inetjuhhh.nl is live) are sent
 * on permanently, to the same page. That way search engines see one site, and logins and the
 * robot check always run on the address they were made for.
 */
class RedirectToMainAddress
{
    public function handle(Request $request, Closure $next): Response
    {
        $main = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (app()->isProduction() && $main && strcasecmp($request->getHost(), $main) !== 0) {
            return redirect()->to(rtrim((string) config('app.url'), '/') . $request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
