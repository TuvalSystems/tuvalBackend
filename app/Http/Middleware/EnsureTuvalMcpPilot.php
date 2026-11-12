<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTuvalMcpPilot
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            config('tuval_mcp.enabled') && app()->environment(['local', 'testing', 'qa', 'staging']),
            404
        );

        return $next($request);
    }
}
