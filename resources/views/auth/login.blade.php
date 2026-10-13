@extends('layouts.guest')

@section('title', 'Iniciar sesión')

@section('content')
<div class="login-page">
    <aside class="login-hero">
        <span class="login-hero__badge">Acceso institucional</span>
        <img src="{{ asset('branding/LOGO-ULEAM-HORIZONTAL.png') }}" alt="ULEAM" style="max-width: 450px; width: 100%; height: auto;">
        <h1> Sistema de Asistencia y Validaciones Académicas (SAVA)</h1>
        <p>Gestión de permisos y justificaciones</p>
    </aside>
    <div class="login-panel">
        <div class="card stack login-card" id="contenido-principal" tabindex="-1">
            <div>
                <h2 style="margin: 0; font-size: 1.35rem;">Iniciar sesión</h2>
                <p class="field-hint" style="margin: 0.4rem 0 0;">
                    Entra con Microsoft 365 para usar tu nombre y correo institucional, o usa la contraseña que te asignó el sistema.
                </p>
            </div>

            @if($errors->any())
                <div class="alert alert--error" role="alert">{{ $errors->first() }}</div>
            @elseif(session('error'))
                <div class="alert alert--error" role="alert">{{ session('error') }}</div>
            @elseif(session('success'))
                <div class="alert alert--success" role="status">{{ session('success') }}</div>
            @endif

            @auth
                <div class="alert alert--warning" role="status">
                    Ahora estás como <strong>{{ auth()->user()->nombreCompleto() }}</strong>
                    ({{ auth()->user()->email }} · {{ auth()->user()->rol->label() }}).
                    Si entras con Secretaría o Decano, se cierra esta sesión y se abre la de esa cuenta.
                </div>
                <form method="POST" action="{{ route('logout') }}" data-no-loading>
                    @csrf
                    <button type="submit" class="btn btn--secondary" style="width: 100%;">Cerrar esta sesión</button>
                </form>
            @endauth

            @if(request('oauth') === 'error')
                <div class="alert alert--error" role="alert">
                    No se pudo iniciar sesión con Microsoft 365. Intenta de nuevo o contacta al administrador.
                </div>
            @endif

            @if(request('solicitud') === 'ok')
                <div class="alert alert--warning" role="status">
                    Solicitud enviada. El Decano revisará tu pedido; cuando sea aprobado podrás iniciar sesión con el correo indicado.
                </div>
            @endif

            <a href="{{ route('auth.microsoft') }}" class="btn btn--secondary" style="width: 100%;" onclick="document.body.insertAdjacentHTML('beforeend', '<div class=\'loading-overlay\'><div class=\'loading-overlay__panel\'><div class=\'loading-overlay__spinner\'></div><span>Conectando con Microsoft 365…</span></div></div>')">
                Ingresar con Microsoft 365
            </a>
            <p class="field-hint" style="margin: -0.35rem 0 0;">
                SAVA toma tu nombre y correo de la cuenta institucional. Para usar otra, cierra sesión y en Microsoft elige “Usar otra cuenta”.
            </p>

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
                    <div class="row" style="justify-content: space-between; align-items: baseline; gap: 0.5rem;">
                        <label for="password" style="margin: 0;">Contraseña</label>
                        <a href="{{ route('recuperar-clave.create') }}" class="field-hint" style="font-size: 0.82rem; text-decoration: none;">
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

                <label class="field" style="flex-direction: row; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }} style="width: auto;">
                    <span class="field-hint" style="margin: 0;">Recordarme en este equipo</span>
                </label>

                <button type="submit" class="btn btn--primary" style="width: 100%;">
                    Entrar al sistema
                </button>
            </form>

            <div class="login-signup-cta">
                <p class="login-signup-cta__label">¿No tienes cuenta todavía?</p>
                <a href="{{ route('solicitar-cuenta.create') }}" class="btn btn--secondary">
                    Solicitar cuenta
                </a>
                <p class="field-hint" style="margin: 0.75rem 0 0; font-size: 0.78rem;">
                    Solo personal autorizado. Tu solicitud será revisada por el Decanato.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
