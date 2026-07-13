<?php

namespace App\Http\Middleware;

use Closure;

class LicenseCors
{
    public function handle($request, Closure $next)
    {
        $response = $request->getMethod() === 'OPTIONS' ? response('', 204) : $next($request);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type');
        $response->headers->set('Access-Control-Max-Age', '86400');
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }
}
