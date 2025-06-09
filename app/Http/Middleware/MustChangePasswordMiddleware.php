<?php

namespace App\Http\Middleware;

use App\Traits\JsonResponseTrait;
use Closure;
use Illuminate\Http\Request;

class MustChangePasswordMiddleware
{
    use JsonResponseTrait;
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && auth()->user()->must_change_password) {
            $this->buildErrorResponse('Debe cambiar su contraseña', 403);
        }
          return $next($request);
    }
}
