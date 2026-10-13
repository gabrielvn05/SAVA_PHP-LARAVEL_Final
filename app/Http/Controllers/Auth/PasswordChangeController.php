<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\StrongPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasswordChangeController extends Controller
{
    public function edit(): View|RedirectResponse
    {
        $user = auth()->user();

        if (! $user->force_password_change) {
            return redirect()->route('dashboard');
        }

        return view('auth.cambiar-clave');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->force_password_change) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'new_password' => ['required', 'string', 'confirmed', new StrongPassword],
        ]);

        $user->update([
            'password' => $validated['new_password'],
            'force_password_change' => false,
        ]);

        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', 'Contraseña actualizada correctamente.');
    }

    public function updateFromProfile(Request $request): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->password) {
            return redirect()
                ->route('perfil.edit')
                ->with('error', 'Tu acceso es con Microsoft 365; la contraseña se gestiona allí.');
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'confirmed', new StrongPassword],
        ], [
            'current_password.required' => 'Indica tu contraseña actual.',
            'new_password.required' => 'Indica la nueva contraseña.',
            'new_password.confirmed' => 'La confirmación no coincide con la nueva contraseña.',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()
                ->withErrors(['current_password' => 'La contraseña actual no es correcta.'])
                ->withInput($request->except('current_password', 'new_password', 'new_password_confirmation'));
        }

        $user->update([
            'password' => $validated['new_password'],
            'force_password_change' => false,
        ]);

        Auth::login($user);

        return redirect()
            ->route('perfil.edit')
            ->with('success', 'Contraseña actualizada correctamente.');
    }
}
