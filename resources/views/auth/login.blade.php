@extends('layouts.guest')

@section('title', 'Iniciar sesión')

@section('content')
<div class="login-page">
    <aside class="login-hero">
        <span class="login-hero__badge">Acceso institucional</span>
        <img class="login-hero__logo" src="{{ asset('branding/LOGO-ULEAM-HORIZONTAL.png') }}" alt="ULEAM">
        <h1> Sistema de Asistencia y Validaciones Académicas (SAVA)</h1>
        <p>Gestión de permisos y justificaciones</p>
    </aside>
    <div class="login-panel">
        <div class="card stack login-card" id="contenido-principal" tabindex="-1">
            <div>
                <h2 class="login-card__title">Iniciar sesión</h2>
                <p class="field-hint login-card__lead">
                    Entra con Microsoft 365 para usar tu nombre y correo institucional, o usa la contraseña que te asignó el sistema.
                </p>
            </div>

            @if($errors->any())
                <x-alert type="error">{{ $errors->first() }}</x-alert>
            @elseif(session('error'))
                <x-alert type="error">{{ session('error') }}</x-alert>
            @elseif(session('success'))
                <x-alert type="success" autohide>{{ session('success') }}</x-alert>
            @endif

            @auth
                <x-alert type="warning">
                    Ahora estás como <strong>{{ auth()->user()->nombreCompleto() }}</strong>
                    ({{ auth()->user()->email }} · {{ auth()->user()->rol->label() }}).
                    Si entras con Secretaría o Decano, se cierra esta sesión y se abre la de esa cuenta.
                </x-alert>
                <form method="POST" action="{{ route('logout') }}" data-no-loading>
                    @csrf
                    <button type="submit" class="btn btn--secondary btn--block">Cerrar esta sesión</button>
                </form>
            @endauth

            @if(request('oauth') === 'error')
                <x-alert type="error">
                    No se pudo iniciar sesión con Microsoft 365. Intenta de nuevo o contacta al administrador.
                </x-alert>
            @endif

            @if(request('solicitud') === 'ok')
                <x-alert type="warning">
                    Solicitud enviada. El Decano revisará tu pedido; cuando sea aprobado podrás iniciar sesión con el correo indicado.
                </x-alert>
            @endif

            <div class="login-microsoft">
                <a href="{{ route('auth.microsoft') }}" class="btn btn--primary btn--block" data-loading-link data-loading-label="Conectando con Microsoft 365…">
                    Ingresar con Microsoft 365
                </a>
                <p class="field-hint">
                    SAVA toma tu nombre y correo de la cuenta institucional. Para usar otra, cierra sesión y en Microsoft elige “Usar otra cuenta”.
                </p>
            </div>

            <p class="login-divider">o con contraseña</p>

            <form method="POST" action="{{ route('login') }}" class="stack" aria-label="Inicio de sesión con correo y contraseña" data-loading-label="Iniciando sesión…">
                @csrf

                <div>
                    <label for="email">Correo institucional</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="usuario@institucion.edu"
                        required
                        autocomplete="email"
                    >
                </div>

                <div>
                    <div class="login-password-row">
                        <label for="password">Contraseña</label>
                        <a href="{{ route('recuperar-clave.create') }}" class="login-forgot">
                            ¿Olvidaste tu contraseña?
                        </a>
                    </div>
                    <div class="password-input-wrap">
                        <input
                            id="password"
                            type="password"
                            name="password"
                            class="password-input-wrap__input"
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="password-input-wrap__toggle" data-password-toggle aria-label="Mostrar contraseña" aria-pressed="false" aria-controls="password">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.75"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <label class="login-remember">
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                    <span class="field-hint">Recordarme en este equipo</span>
                </label>

                <button type="submit" class="btn btn--secondary btn--block">
                    Entrar al sistema
                </button>
            </form>

            <div class="login-signup-cta">
                <p class="login-signup-cta__label">¿No tienes cuenta todavía?</p>
                <a href="{{ route('solicitar-cuenta.create') }}" class="btn btn--secondary">
                    Solicitar cuenta
                </a>
                <p class="field-hint login-signup-cta__note">
                    Solo personal autorizado. Tu solicitud será revisada por el Decanato.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
