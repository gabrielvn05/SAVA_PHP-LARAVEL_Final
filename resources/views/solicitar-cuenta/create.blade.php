@extends('layouts.guest')

@section('title', 'Solicitar cuenta')

@php
    $mensajes = [
        'usuario_existe' => 'Este correo ya está registrado. Inicia sesión con Office 365.',
        'solicitud_pendiente' => 'Ya existe una solicitud pendiente para este correo.',
        'datos_incompletos' => 'Completa todos los campos obligatorios.',
        'cedula_letras' => 'La cédula solo debe contener números (sin letras).',
        'cedula_formato' => 'La cédula ecuatoriana debe tener exactamente 10 dígitos.',
        'cedula_invalida' => 'La cédula no es válida. Verifica que sea tu número de identidad real.',
        'celular_invalido' => 'Indica un celular válido (mínimo 9 dígitos).',
        'carrera_invalida' => 'Selecciona una carrera válida.',
        'rol_invalido' => 'Selecciona un rol válido.',
        'correo_invalido' => 'Indica un correo electrónico válido.',
        'correo_no_institucional' => 'Use su correo institucional ULEAM. No se aceptan correos temporales.',
    ];
    $mensaje = $mensajes[$aviso ?? ''] ?? null;
@endphp

@section('content')
<div class="login-page">
    <aside class="login-hero">
        <span class="login-hero__badge">Acceso institucional</span>
        <img src="{{ asset('branding/LOGO-ULEAM-HORIZONTAL.png') }}" alt="ULEAM" style="max-width: 450px; width: 100%; height: auto;">
        <h1>Solicitar cuenta</h1>
        <p>Si trabajas con la facultad y aún no tienes acceso, envía tu solicitud. El Decanato la revisará.</p>
    </aside>
    <div class="login-panel">
        <div class="card stack" style="width: 100%; max-width: 760px;">
            <div>
                <h2 style="margin: 0; font-size: 1.35rem;">Datos de la solicitud</h2>
                <p class="field-hint" style="margin: 0.4rem 0 0;">Usa tu correo institucional (@uleam.edu.ec o @live.uleam.edu.ec).</p>
            </div>

            <p class="field-hint">
                ¿Ya tienes cuenta? <a href="{{ route('login') }}" style="font-weight: 600;">Inicia sesión aquí</a>.
            </p>

            @if($mensaje)
                <div class="alert alert--error" role="alert">{{ $mensaje }}</div>
            @endif

            <form method="POST" action="{{ route('solicitar-cuenta.store') }}" class="stack" id="solicitar-cuenta-form">
                @csrf
                <div class="form-grid">
                    <label class="field">
                        <span class="field__label">Nombres *</span>
                        <input type="text" name="nombres" required class="field__input" value="{{ old('nombres') }}" autocomplete="given-name">
                    </label>
                    <label class="field">
                        <span class="field__label">Apellidos *</span>
                        <input type="text" name="apellidos" required class="field__input" value="{{ old('apellidos') }}" autocomplete="family-name">
                    </label>
                    <label class="field">
                        <span class="field__label">Cédula de identidad *</span>
                        <input
                            type="text"
                            name="cedula"
                            id="cedula-input"
                            required
                            class="field__input"
                            value="{{ old('cedula') }}"
                            inputmode="numeric"
                            maxlength="10"
                            pattern="\d{10}"
                            autocomplete="off"
                            aria-describedby="cedula-hint"
                        >
                        <p class="field-hint" id="cedula-hint" style="margin: 0.35rem 0 0;">
                            10 dígitos numéricos (cédula ecuatoriana). Ejemplo: 1315591303. No use letras ni números repetidos ficticios.
                        </p>
                    </label>
                    <label class="field">
                        <span class="field__label">Celular *</span>
                        <input type="tel" name="celular" required class="field__input" value="{{ old('celular') }}" inputmode="tel" autocomplete="tel">
                    </label>
                    <label class="field field--full">
                        <span class="field__label">Correo institucional *</span>
                        <input type="email" name="email" required class="field__input" value="{{ old('email') }}" autocomplete="email" placeholder="nombre@uleam.edu.ec">
                    </label>
                    <label class="field">
                        <span class="field__label">Carrera *</span>
                        <select name="carrera" required class="field__input">
                            <option value="">Seleccionar…</option>
                            @foreach($carreras as $carrera)
                                <option value="{{ $carrera['value'] }}" @selected(old('carrera') === $carrera['value'])>
                                    {{ $carrera['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span class="field__label">Rol solicitado *</span>
                        <select name="rol_solicitado" required class="field__input">
                            @foreach($roles as $rol)
                                <option value="{{ $rol->value }}" @selected(old('rol_solicitado', 'administrativo') === $rol->value)>
                                    {{ $rol->label() }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div class="row" style="gap: 8px; flex-wrap: wrap;">
                    <button type="submit" class="btn btn--primary">Enviar solicitud</button>
                    <a href="{{ route('login') }}" class="btn btn--secondary">Volver al login</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    (function () {
        const input = document.getElementById('cedula-input');
        if (!input) {
            return;
        }

        const sanitize = () => {
            const hadLetters = /[a-zA-Z]/.test(input.value);
            const digits = input.value.replace(/\D/g, '').slice(0, 10);
            if (input.value !== digits) {
                input.value = digits;
            }
            input.setCustomValidity(hadLetters ? 'La cédula no puede contener letras.' : '');
        };

        input.addEventListener('input', sanitize);
        input.addEventListener('blur', sanitize);
    })();
</script>
@endsection
