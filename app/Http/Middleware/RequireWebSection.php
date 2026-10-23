<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireWebSection
{
    public function handle(Request $request, Closure $next, string ...$sections)
    {
        // Legacy internal web endpoints remain unchanged by explicit deployment policy.
        if (!$request->is('api/*')) {
            return $next($request);
        }

        $user = $request->user();
        abort_unless($user && $user->status && $user->perfil?->state === 'active', 403);

        // Apply the same active section grants used to build the user's web menu.
        $allowed = $user->perfil->sections()
            ->where('sections.platform', 'web')
            ->where('sections.status', 1)
            ->wherePivot('state', 1)
            ->whereIn('sections.route_name', $sections)
            ->exists();

        abort_unless($allowed, 403, 'No tiene permiso para administrar este recurso.');

        return $next($request);
    }
}
