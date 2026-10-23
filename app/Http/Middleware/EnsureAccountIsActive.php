<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user && $user->status, 403, 'La cuenta no está activa.');

        $mobile = $request->is('api/movil/*');
        $profile = $mobile ? $user->perfilApp : $user->perfil;
        abort_unless($profile && $profile->state === 'active', 403, 'El perfil no está activo.');
        abort_if($mobile && !$user->app_access, 403, 'El acceso móvil está deshabilitado.');

        return $next($request);
    }
}
