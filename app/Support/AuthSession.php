<?php

namespace App\Support;

use App\Enums\AppRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class AuthSession
{
    public static function login(User $user, bool $remember = false): void
    {
        $request = request();

        Auth::logout();

        if ($request->hasSession()) {
            $request->session()->forget('url.intended');
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();

        SingleSessionEnforcer::closeOtherSessions($user, $request->session()->getId());
    }

    public static function redirectHome(User $user, ?string $success = null): RedirectResponse
    {
        if ($user->force_password_change) {
            return redirect()->route('cambiar-clave.edit');
        }

        if ($user->needsProfileCompletion()) {
            return redirect()->route('perfil.completar');
        }

        $redirect = in_array($user->rol, [AppRole::Secretaria, AppRole::Decano], true)
            ? redirect()->route('solicitudes.proceso')
            : redirect()->route('dashboard');

        if ($success) {
            $redirect->with('success', $success);
        }

        return $redirect;
    }
}
