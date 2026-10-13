<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && $user->needsProfileCompletion()
            && ! $request->routeIs(
                'login',
                'login.store',
                'auth.microsoft',
                'auth.microsoft.callback',
                'perfil.completar',
                'perfil.update',
                'logout',
                'cambiar-clave.*',
            )
        ) {
            return redirect()->route('perfil.completar');
        }

        return $next($request);
    }
}
