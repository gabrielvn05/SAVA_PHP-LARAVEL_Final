<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccesoResultado;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccesoEventoService;
use App\Support\AuthSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly AccesoEventoService $accesos) {}

    public function create(): View|RedirectResponse
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower($validated['email']);
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (
            ! $user
            || ! $user->password
            || ! Hash::check($validated['password'], $user->password)
        ) {
            $this->accesos->registrar(AccesoResultado::ClaveIncorrecta, $user, $email);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Credenciales incorrectas o usuario sin contraseña asignada.',
                ]);
        }

        if (! $user->activo) {
            $this->accesos->registrar(AccesoResultado::CuentaInactiva, $user, $email);

            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Tu cuenta está inactiva. Contacta al administrador.');
        }

        AuthSession::login($user, $request->boolean('remember'));
        $this->accesos->registrar(AccesoResultado::Exito, $user, $email);

        return AuthSession::redirectHome($user);
    }
}
