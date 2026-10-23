<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        if ($request->bearerToken() || $request->has('_token')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
