<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\MicrosoftAccountService;
use App\Support\AuthSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class MicrosoftAuthController extends Controller
{
    public function __construct(private readonly MicrosoftAccountService $accounts) {}

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('azure')
            ->scopes(['openid', 'profile', 'email', 'User.Read'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $azureUser = Socialite::driver('azure')->user();
        } catch (Throwable $e) {
            Log::warning('Fallo OAuth Microsoft 365', ['error' => $e->getMessage()]);

            return redirect()->route('login')
                ->with('error', 'No se pudo completar el inicio de sesión con Microsoft 365. Intenta de nuevo.');
        }

        $result = $this->accounts->findOrCreateFromAzure($azureUser);

        if (! $result['ok']) {
            return redirect()->route('login')->with('error', $result['error']);
        }

        AuthSession::login($result['user'], remember: true);

        return AuthSession::redirectHome($result['user']);
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Sesión cerrada. Puedes entrar con otra cuenta (Secretaría, Decano o Microsoft 365).');
    }
}
