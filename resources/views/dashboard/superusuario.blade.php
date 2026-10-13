@extends('layouts.app')

@section('title', 'Superusuario')

@section('content')
<section class="stack">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Superusuario</h1>
            <p class="field-hint" style="margin: 0.35rem 0 0;">
                Acceso de contingencia: gestión de cuentas, roles y solicitudes de alta.
            </p>
        </div>
    </header>

    <div class="form-grid form-grid--2">
        <a href="{{ route('admin.usuarios.index') }}" class="card" style="text-decoration: none; color: inherit;">
            <h2 style="margin: 0 0 0.35rem; font-size: 1.05rem;">Usuarios y roles</h2>
            <p class="field-hint" style="margin: 0;">Alta, edición, delegación y capacidades del personal.</p>
        </a>
        <a href="{{ route('admin.solicitudes-cuenta.index') }}" class="card" style="text-decoration: none; color: inherit;">
            <h2 style="margin: 0 0 0.35rem; font-size: 1.05rem;">Solicitudes de cuenta</h2>
            <p class="field-hint" style="margin: 0;">Aprobar o rechazar registros pendientes.</p>
        </a>
        <a href="{{ route('solicitudes.proceso') }}" class="card" style="text-decoration: none; color: inherit;">
            <h2 style="margin: 0 0 0.35rem; font-size: 1.05rem;">Bandeja de trámites</h2>
            <p class="field-hint" style="margin: 0;">Supervisión del flujo de solicitudes institucionales.</p>
        </a>
        <a href="{{ route('reportes.index') }}" class="card" style="text-decoration: none; color: inherit;">
            <h2 style="margin: 0 0 0.35rem; font-size: 1.05rem;">Reportes</h2>
            <p class="field-hint" style="margin: 0;">Exportación y consulta agregada.</p>
        </a>
    </div>

    <article class="card stack" style="gap: 0.65rem;">
        <h2 style="margin: 0; font-size: 1rem;">Buenas prácticas de seguridad</h2>
        <ul class="field-hint" style="margin: 0; padding-left: 1.15rem;">
            <li>Use una contraseña larga y exclusiva; cámbiela periódicamente.</li>
            <li>No comparta la sesión; cierre con <strong>Cerrar sesión</strong> al terminar.</li>
            <li>Limite el rol superusuario: idealmente una o dos cuentas de respaldo.</li>
            <li>Revise periódicamente la tabla <code>audit_log</code> (IP, acciones sensibles).</li>
            <li>En producción: HTTPS obligatorio, copias de respaldo y variables <code>SUPERUSER_*</code> solo en el servidor.</li>
        </ul>
    </article>
</section>
@endsection
