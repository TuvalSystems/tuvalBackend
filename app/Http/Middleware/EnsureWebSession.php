<?php
namespace App\Http\Middleware;
use App\Models\GpUsuario;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class EnsureWebSession
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(Auth::guard('web')->check(), 401);
        abort_unless(GpUsuario::query()->activoWeb()->whereKey(Auth::guard('web')->id())->exists(), 403);
        return $next($request);
    }
}
