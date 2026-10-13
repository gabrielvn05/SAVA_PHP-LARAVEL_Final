@extends('layouts.guest')

@section('title', 'Recuperar contraseña')

@section('content')
<div class="login-page">
    <aside class="login-hero">
        <span class="login-hero__badge">Acceso institucional</span>
        <img src="{{ asset('branding/LOGO-ULEAM-HORIZONTAL.png') }}" alt="ULEAM" style="max-width: 450px; width: 100%; height: auto;">
        <h1>Recuperar acceso</h1>
        <p>Te enviaremos una clave temporal al correo registrado en SAVA.</p>
    </aside>
    <div class="login-panel">
        <div class="card stack login-card" id="contenido-principal" tabindex="-1">
            <div>
                <h2 style="margin: 0; font-size: 1.35rem;">Recuperar contraseña</h2>
                <p class="field-hint" style="margin: 0.4rem 0 0;">
                    Indica el correo con el que inicias sesión. Si la cuenta usa solo Microsoft 365, entra por ese método.
                </p>
            </div>

            @if($errors->any())
                <div class="alert alert--error" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('recuperar-clave.store') }}" class="stack" data-loading-label="Enviando…">
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

                <button type="submit" class="btn btn--primary" style="width: 100%;">
                    Enviar clave temporal
                </button>
            </form>

            <p class="field-hint" style="margin: 0; text-align: center;">
                <a href="{{ route('login') }}">Volver al inicio de sesión</a>
            </p>
        </div>
    </div>
</div>
@endsection
