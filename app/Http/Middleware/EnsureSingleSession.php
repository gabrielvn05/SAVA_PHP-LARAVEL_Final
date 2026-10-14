<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSingleSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $esperado = $user
            ? (string) ($user->newQuery()->whereKey($user->id)->value('session_token') ?? '')
            : '';

        if ($user && $esperado !== '' && $request->session()->get('auth_session_token') !== $esperado) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Tu sesión se cerró porque iniciaste sesión en otro dispositivo.');
        }

        return $next($request);
    }
}
