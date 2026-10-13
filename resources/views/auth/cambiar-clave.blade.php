@extends('layouts.guest')

@section('title', 'Cambiar contraseña')

@section('content')
<div class="login-page">
    <aside class="login-hero">
        <span class="login-hero__badge">Acceso institucional</span>
        <img src="{{ asset('branding/LOGO-ULEAM-HORIZONTAL.png') }}" alt="ULEAM" style="max-width: 450px; width: 100%; height: auto;">
        <h1>Cambio de contraseña</h1>
        <p>Por seguridad, debes actualizar la clave temporal antes de continuar.</p>
    </aside>
    <div class="login-panel">
        <div class="card stack login-card" id="contenido-principal" tabindex="-1">
            <div>
                <h2 style="margin: 0; font-size: 1.35rem;">Nueva contraseña</h2>
                <p class="field-hint" style="margin: 0.4rem 0 0;">Mínimo 8 caracteres, con mayúscula, minúscula, número y carácter especial.</p>
            </div>

            @if($errors->any())
                <div class="alert alert--error" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('cambiar-clave.update') }}" class="stack" data-loading-label="Guardando…">
                @csrf
                @method('PUT')

                <div>
                    <label for="new_password">Nueva contraseña</label>
                    <div class="password-input-wrap">
                        <input id="new_password" type="password" name="new_password" class="password-input-wrap__input" required minlength="8" autocomplete="new-password">
                        <button type="button" class="password-input-wrap__toggle" data-password-toggle aria-label="Mostrar contraseña" aria-pressed="false">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.75"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div>
                    <label for="new_password_confirmation">Confirmar contraseña</label>
                    <div class="password-input-wrap">
                        <input id="new_password_confirmation" type="password" name="new_password_confirmation" class="password-input-wrap__input" required minlength="8" autocomplete="new-password">
                        <button type="button" class="password-input-wrap__toggle" data-password-toggle aria-label="Mostrar contraseña" aria-pressed="false">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.75"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn--primary" style="width: 100%;">Guardar nueva contraseña</button>
            </form>
        </div>
    </div>
</div>
@endsection
