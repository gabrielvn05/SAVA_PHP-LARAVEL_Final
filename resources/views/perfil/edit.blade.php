@extends('layouts.app')

@section('title', 'Perfil y configuración')

@php
    $identityLocked = trim((string) $user->cedula) !== '';
    $iniciales = collect(preg_split('/\s+/', trim($user->nombres.' '.$user->apellidos)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
    $tieneClaveLocal = (bool) $user->password;
@endphp

@section('content')
<section class="stack page-enter profile-page">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Perfil y configuración</h1>
        </div>
    </header>

    @if(session('success'))
        <div class="alert alert--success" role="status">{{ session('success') }}</div>
    @elseif(session('error'))
        <div class="alert alert--error" role="alert">{{ session('error') }}</div>
    @endif

    <div class="profile-page__layout">
        <aside class="card card--flat profile-identity">
            <div class="profile-identity__hero">
                <div class="profile-identity__avatar" aria-hidden="true">{{ $iniciales !== '' ? $iniciales : '?' }}</div>
                <h2 class="profile-identity__name">{{ mb_strtoupper($user->nombreCompleto()) }}</h2>
                @if($identityLocked)
                    <p class="profile-identity__meta">{{ $user->cedula }}</p>
                @endif
            </div>

            <div class="profile-identity__body">
                <dl class="profile-identity__facts">
                    <div class="profile-identity__fact">
                        <div class="profile-identity__fact-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                <rect x="3" y="6" width="18" height="13" rx="2" stroke="currentColor" stroke-width="1.75"/>
                                <circle cx="9" cy="12" r="2" stroke="currentColor" stroke-width="1.75"/>
                                <path d="M14 11h4M14 14h3" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div class="profile-identity__fact-text">
                            <dt>Cédula</dt>
                            <dd>{{ $identityLocked ? $user->cedula : '—' }}</dd>
                        </div>
                    </div>
                    <div class="profile-identity__fact">
                        <div class="profile-identity__fact-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.75"/>
                                <path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div class="profile-identity__fact-text">
                            <dt>Nombres completos</dt>
                            <dd>{{ $user->nombreCompleto() }}</dd>
                        </div>
                    </div>
                    <div class="profile-identity__fact">
                        <div class="profile-identity__fact-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                <path d="M12 3 14.5 8.5 20.5 9.2 16 13.2 17.2 19 12 16.2 6.8 19 8 13.2 3.5 9.2 9.5 8.5 12 3Z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div class="profile-identity__fact-text">
                            <dt>Rol</dt>
                            <dd>{{ $user->rol->label() }}</dd>
                        </div>
                    </div>
                </dl>

                @if($identityLocked)
                    <p class="profile-identity__lock">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <rect x="5" y="11" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.75"/>
                            <path d="M8 11V8a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                        </svg>
                        Estos datos de identidad no se pueden modificar.
                    </p>
                @else
                    <p class="profile-identity__lock profile-identity__lock--hint">
                        Completa tu cédula en el formulario; luego quedará fijada como dato de identidad.
                    </p>
                @endif
            </div>
        </aside>

        <div class="profile-page__main stack">
            <article class="card profile-contact" data-profile-editable>
                <header class="profile-contact__header">
                    <div>
                        <h2 class="profile-contact__title">Contacto y datos institucionales</h2>
                        <p class="profile-contact__subtitle">Esta información sí puede ser actualizada</p>
                    </div>
                </header>

                @if($errors->any() && ! $errors->has('current_password') && ! $errors->has('new_password'))
                    <div class="alert alert--error" role="alert">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('perfil.update') }}" class="stack profile-contact__form" data-loading-label="Guardando perfil…">
                    @csrf
                    @method('PUT')

                    @if($identityLocked)
                        <input type="hidden" name="cedula" value="{{ $user->cedula }}">
                    @endif

                    @include('perfil._campos', [
                        'user' => $user,
                        'carreras' => $carreras,
                        'showCedula' => ! $identityLocked,
                        'readonly' => $identityLocked,
                        'profileLayout' => true,
                        'showEmail' => true,
                    ])

                    <div class="profile-contact__actions">
                        @if($identityLocked)
                            <button type="button" class="btn btn--primary" data-profile-edit-toggle>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M12 20h9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                                    <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
                                </svg>
                                Editar datos
                            </button>
                            <button type="submit" class="btn btn--primary" data-profile-save hidden>
                                Guardar cambios
                            </button>
                        @else
                            <button type="submit" class="btn btn--primary">
                                Guardar cambios
                            </button>
                        @endif
                    </div>
                </form>
            </article>

            @if($tieneClaveLocal)
                <article class="card profile-security stack">
                    <header>
                        <h2 class="profile-contact__title">Seguridad</h2>
                        <p class="profile-contact__subtitle">Cambia tu contraseña cuando lo necesites</p>
                    </header>

                    @if($errors->has('current_password') || $errors->has('new_password'))
                        <div class="alert alert--error" role="alert">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('perfil.clave.update') }}" class="stack profile-contact__form profile-security__form" data-loading-label="Actualizando contraseña…">
                        @csrf
                        @method('PUT')

                        <div class="form-grid form-grid--2">
                            <label class="field">
                                <span class="field__label">Contraseña actual</span>
                                <div class="password-input-wrap">
                                    <input type="password" name="current_password" class="password-input-wrap__input field__input profile-field__input" required autocomplete="current-password">
                                    <button type="button" class="password-input-wrap__toggle" data-password-toggle aria-label="Mostrar contraseña" aria-pressed="false">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.75"/>
                                        </svg>
                                    </button>
                                </div>
                                @error('current_password') <p class="field-hint" style="color: var(--color-danger);">{{ $message }}</p> @enderror
                            </label>
                        </div>

                        <div class="form-grid form-grid--2">
                            <label class="field">
                                <span class="field__label">Nueva contraseña</span>
                                <div class="password-input-wrap">
                                    <input id="profile_new_password" type="password" name="new_password" class="password-input-wrap__input field__input profile-field__input" required minlength="9" autocomplete="new-password" aria-describedby="password-strength-profile">
                                    <button type="button" class="password-input-wrap__toggle" data-password-toggle aria-label="Mostrar contraseña" aria-pressed="false">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.75"/>
                                        </svg>
                                    </button>
                                </div>
                                <div id="password-strength-profile">
                                    @include('partials.password-strength', ['inputId' => 'profile_new_password'])
                                </div>
                            </label>
                            <label class="field">
                                <span class="field__label">Confirmar nueva contraseña</span>
                                <div class="password-input-wrap">
                                    <input type="password" name="new_password_confirmation" class="password-input-wrap__input field__input profile-field__input" required minlength="9" autocomplete="new-password">
                                    <button type="button" class="password-input-wrap__toggle" data-password-toggle aria-label="Mostrar contraseña" aria-pressed="false">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.75"/>
                                        </svg>
                                    </button>
                                </div>
                                @error('new_password') <p class="field-hint" style="color: var(--color-danger);">{{ $message }}</p> @enderror
                            </label>
                        </div>

                        <p class="field-hint" style="margin: 0;">Más de 8 caracteres, con mayúscula, minúscula, número y carácter especial.</p>

                        <div class="profile-contact__actions">
                            <button type="submit" class="btn btn--primary">Actualizar contraseña</button>
                        </div>
                    </form>
                </article>
            @else
                <article class="card profile-security stack">
                    <h2 class="profile-contact__title">Seguridad</h2>
                    <p class="field-hint" style="margin: 0;">
                        Inicias sesión con Microsoft 365; la contraseña se administra en tu cuenta institucional.
                    </p>
                </article>
            @endif
        </div>
    </div>
</section>
@endsection
