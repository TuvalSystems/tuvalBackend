<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;


class CheckTokenAndAddToHeaderMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $all = $request->all();
        if (isset($all['_token'])) {
            $request->headers->set('Authorization', sprintf('%s %s', 'Bearer', $all['_token']));
        }
        return $next($request);
    }
}
