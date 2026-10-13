<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->force_password_change && ! $request->routeIs('login', 'login.store', 'auth.microsoft', 'auth.microsoft.callback', 'cambiar-clave.*', 'logout')) {
            return redirect()->route('cambiar-clave.edit');
        }

        return $next($request);
    }
}
