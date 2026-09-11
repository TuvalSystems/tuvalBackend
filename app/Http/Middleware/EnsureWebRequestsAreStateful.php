<?php
namespace App\Http\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
class EnsureWebRequestsAreStateful extends EnsureFrontendRequestsAreStateful
{
    public function handle($request, $next)
    {
        // La API móvil conserva autenticación Bearer sin cookies ni CSRF de SPA.
        if ($request->is('api/movil', 'api/movil/*')) return $next($request);
        return parent::handle($request, $next);
    }
}
