<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordRecoveryMail;
use App\Models\User;
use App\Support\TemporaryPasswordGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PasswordRecoveryController extends Controller
{
    public function create(): View
    {
        return view('auth.recuperar-clave');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower($validated['email']);
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user && $user->activo && $user->password) {
            $temporaryPassword = TemporaryPasswordGenerator::generate();

            $user->update([
                'password' => Hash::make($temporaryPassword),
                'force_password_change' => true,
            ]);

            try {
                Mail::to($user->email)->send(new PasswordRecoveryMail(
                    $user->nombreCompleto(),
                    $user->email,
                    $temporaryPassword,
                ));
            } catch (\Throwable) {
                // No revelar el fallo de correo al solicitante.
            }
        }

        return redirect()
            ->route('login')
            ->with(
                'success',
                'Si existe una cuenta con contraseña asociada a ese correo, recibirás una clave temporal. Revisa tu bandeja de entrada.',
            );
    }
}
