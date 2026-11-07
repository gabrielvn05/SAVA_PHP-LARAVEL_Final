@extends('layouts.app')

@section('title', 'Superusuario')

@section('content')
<section class="stack">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Superusuario</h1>
            <p class="field-hint" style="margin: 0.35rem 0 0;">
                Acceso de contingencia: cuentas, sesiones, delegaciones y supervisión del trámite.
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
        <a href="{{ route('admin.cuentas.index') }}" class="card" style="text-decoration: none; color: inherit;">
            <h2 style="margin: 0 0 0.35rem; font-size: 1.05rem;">Cuentas por atender</h2>
            <p class="field-hint" style="margin: 0;">{{ $resumen['inactivas'] }} inactivas, {{ $resumen['claves'] }} claves temporales, {{ $resumen['altas'] }} altas pendientes.</p>
        </a>
        <a href="{{ route('admin.delegaciones.index') }}" class="card" style="text-decoration: none; color: inherit;">
            <h2 style="margin: 0 0 0.35rem; font-size: 1.05rem;">Delegaciones</h2>
            <p class="field-hint" style="margin: 0;">{{ $resumen['delegaciones'] }} funciones del Decano otorgadas a otras cuentas.</p>
        </a>
        <a href="{{ route('admin.accesos.index') }}" class="card" style="text-decoration: none; color: inherit;">
            <h2 style="margin: 0 0 0.35rem; font-size: 1.05rem;">Accesos y sesiones</h2>
            <p class="field-hint" style="margin: 0;">Ingresos, intentos fallidos y cierre de sesión. Abiertas ahora: {{ $resumen['sesiones'] }}.</p>
        </a>
        <a href="{{ route('admin.auditoria.index') }}" class="card" style="text-decoration: none; color: inherit;">
            <h2 style="margin: 0 0 0.35rem; font-size: 1.05rem;">Auditoría</h2>
            <p class="field-hint" style="margin: 0;">Logs de todas las cuentas: acciones, IP y cambios.</p>
        </a>
        <a href="{{ route('solicitudes.proceso') }}" class="card" style="text-decoration: none; color: inherit;">
            <h2 style="margin: 0 0 0.35rem; font-size: 1.05rem;">Bandeja de trámites</h2>
            <p class="field-hint" style="margin: 0;">Supervisión del flujo de solicitudes institucionales.</p>
        </a>
        <a href="{{ route('notificaciones-faltas.index') }}" class="card" style="text-decoration: none; color: inherit;">
            <h2 style="margin: 0 0 0.35rem; font-size: 1.05rem;">Notificaciones TH</h2>
            <p class="field-hint" style="margin: 0;">Oficios de faltas emitidos y reenvíos.</p>
        </a>
        <a href="{{ route('reportes.index') }}" class="card" style="text-decoration: none; color: inherit;">
            <h2 style="margin: 0 0 0.35rem; font-size: 1.05rem;">Reportes</h2>
            <p class="field-hint" style="margin: 0;">Exportación y consulta agregada.</p>
        </a>
        <a href="{{ route('biblioteca.index') }}" class="card" style="text-decoration: none; color: inherit;">
            <h2 style="margin: 0 0 0.35rem; font-size: 1.05rem;">Biblioteca</h2>
            <p class="field-hint" style="margin: 0;">Oficios institucionales de consulta.</p>
        </a>
    </div>

    <article class="card stack" style="gap: 0.65rem;">
        <h2 style="margin: 0; font-size: 1rem;">Buenas prácticas de seguridad</h2>
        <ul class="field-hint" style="margin: 0; padding-left: 1.15rem;">
            <li>Use una contraseña larga y exclusiva; cámbiela periódicamente.</li>
            <li>No comparta la sesión; cierre con <strong>Cerrar sesión</strong> al terminar.</li>
            <li>Limite el rol superusuario: idealmente una o dos cuentas de respaldo.</li>
            <li>Revise periódicamente <a href="{{ route('admin.auditoria.index') }}">Auditoría</a> (IP y acciones de todas las cuentas).</li>
            <li>En producción: HTTPS obligatorio, copias de respaldo y variables <code>SUPERUSER_*</code> solo en el servidor.</li>
        </ul>
    </article>
</section>
@endsection
