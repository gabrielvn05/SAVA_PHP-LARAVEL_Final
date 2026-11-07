@extends('layouts.app')

@section('title', 'Superusuario')

@section('content')
<section class="stack dashboard-page super-dash">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Superusuario</h1>
            <p class="page-header__subtitle">
                Acceso de contingencia: cuentas, sesiones, delegaciones y supervisión del trámite.
            </p>
        </div>
    </header>

    <div class="dash-metrics">
        <a href="{{ route('admin.cuentas.index') }}" class="card dash-metric dash-metric--warning super-dash__metric">
            <span class="dash-metric__label">Inactivas</span>
            <strong class="dash-metric__value">{{ $resumen['inactivas'] }}</strong>
            <p class="dash-metric__hint">Cuentas desactivadas</p>
        </a>
        <a href="{{ route('admin.cuentas.index') }}" class="card dash-metric dash-metric--info super-dash__metric">
            <span class="dash-metric__label">Claves temporales</span>
            <strong class="dash-metric__value">{{ $resumen['claves'] }}</strong>
            <p class="dash-metric__hint">Aún sin cambiar</p>
        </a>
        <a href="{{ route('admin.solicitudes-cuenta.index') }}" class="card dash-metric dash-metric--primary super-dash__metric">
            <span class="dash-metric__label">Altas pendientes</span>
            <strong class="dash-metric__value">{{ $resumen['altas'] }}</strong>
            <p class="dash-metric__hint">Solicitudes de cuenta</p>
        </a>
        <a href="{{ route('admin.delegaciones.index') }}" class="card dash-metric super-dash__metric" style="border-left-color: var(--color-accent);">
            <span class="dash-metric__label">Delegaciones</span>
            <strong class="dash-metric__value">{{ $resumen['delegaciones'] }}</strong>
            <p class="dash-metric__hint">Funciones del Decano</p>
        </a>
        <a href="{{ route('admin.accesos.index') }}" class="card dash-metric dash-metric--success super-dash__metric">
            <span class="dash-metric__label">Sesiones abiertas</span>
            <strong class="dash-metric__value">{{ $resumen['sesiones'] }}</strong>
            <p class="dash-metric__hint">Accesos activos ahora</p>
        </a>
    </div>

    <div class="super-dash__block">
        <h2 class="super-dash__heading">Cuentas</h2>
        <div class="super-dash__grid">
            <a href="{{ route('admin.usuarios.index') }}" class="card super-dash__card">
                <span class="super-dash__glyph">@include('partials.nav-icon', ['name' => 'users', 'class' => 'super-dash__icon'])</span>
                <span class="super-dash__copy">
                    <h2>Usuarios y roles</h2>
                    <p class="field-hint">Alta, edición, delegación y capacidades del personal.</p>
                </span>
            </a>
            <a href="{{ route('admin.solicitudes-cuenta.index') }}" class="card super-dash__card super-dash__card--info">
                <span class="super-dash__glyph">@include('partials.nav-icon', ['name' => 'user-plus', 'class' => 'super-dash__icon'])</span>
                <span class="super-dash__copy">
                    <h2>Solicitudes de cuenta</h2>
                    <p class="field-hint">Aprobar o rechazar registros pendientes.</p>
                </span>
            </a>
            <a href="{{ route('admin.cuentas.index') }}" class="card super-dash__card super-dash__card--warning">
                <span class="super-dash__glyph">@include('partials.nav-icon', ['name' => 'alarm', 'class' => 'super-dash__icon'])</span>
                <span class="super-dash__copy">
                    <h2>Cuentas por atender</h2>
                    <p class="field-hint">{{ $resumen['inactivas'] }} inactivas, {{ $resumen['claves'] }} claves temporales, {{ $resumen['altas'] }} altas pendientes.</p>
                </span>
            </a>
        </div>
    </div>

    <div class="super-dash__block">
        <h2 class="super-dash__heading">Seguridad</h2>
        <div class="super-dash__grid">
            <a href="{{ route('admin.accesos.index') }}" class="card super-dash__card super-dash__card--success">
                <span class="super-dash__glyph">@include('partials.nav-icon', ['name' => 'shield', 'class' => 'super-dash__icon'])</span>
                <span class="super-dash__copy">
                    <h2>Accesos y sesiones</h2>
                    <p class="field-hint">Ingresos, intentos fallidos y cierre de sesión. Abiertas ahora: {{ $resumen['sesiones'] }}.</p>
                </span>
            </a>
            <a href="{{ route('admin.auditoria.index') }}" class="card super-dash__card">
                <span class="super-dash__glyph">@include('partials.nav-icon', ['name' => 'audit', 'class' => 'super-dash__icon'])</span>
                <span class="super-dash__copy">
                    <h2>Auditoría</h2>
                    <p class="field-hint">Logs de todas las cuentas: acciones, IP y cambios.</p>
                </span>
            </a>
            <a href="{{ route('admin.delegaciones.index') }}" class="card super-dash__card super-dash__card--accent">
                <span class="super-dash__glyph">@include('partials.nav-icon', ['name' => 'key', 'class' => 'super-dash__icon'])</span>
                <span class="super-dash__copy">
                    <h2>Delegaciones</h2>
                    <p class="field-hint">{{ $resumen['delegaciones'] }} funciones del Decano otorgadas a otras cuentas.</p>
                </span>
            </a>
        </div>
    </div>

    <div class="super-dash__block">
        <h2 class="super-dash__heading">Supervisión</h2>
        <div class="super-dash__grid">
            <a href="{{ route('solicitudes.proceso') }}" class="card super-dash__card super-dash__card--info">
                <span class="super-dash__glyph">@include('partials.nav-icon', ['name' => 'clipboard', 'class' => 'super-dash__icon'])</span>
                <span class="super-dash__copy">
                    <h2>Bandeja de trámites</h2>
                    <p class="field-hint">Supervisión del flujo de solicitudes institucionales.</p>
                </span>
            </a>
            <a href="{{ route('notificaciones-faltas.index') }}" class="card super-dash__card super-dash__card--warning">
                <span class="super-dash__glyph">@include('partials.nav-icon', ['name' => 'bell', 'class' => 'super-dash__icon'])</span>
                <span class="super-dash__copy">
                    <h2>Notificaciones TH</h2>
                    <p class="field-hint">Oficios de faltas emitidos y reenvíos.</p>
                </span>
            </a>
            <a href="{{ route('reportes.index') }}" class="card super-dash__card">
                <span class="super-dash__glyph">@include('partials.nav-icon', ['name' => 'chart', 'class' => 'super-dash__icon'])</span>
                <span class="super-dash__copy">
                    <h2>Reportes</h2>
                    <p class="field-hint">Exportación y consulta agregada.</p>
                </span>
            </a>
            <a href="{{ route('biblioteca.index') }}" class="card super-dash__card super-dash__card--success">
                <span class="super-dash__glyph">@include('partials.nav-icon', ['name' => 'book', 'class' => 'super-dash__icon'])</span>
                <span class="super-dash__copy">
                    <h2>Biblioteca</h2>
                    <p class="field-hint">Oficios institucionales de consulta.</p>
                </span>
            </a>
        </div>
    </div>

    <article class="card super-dash__note">
        <h2>Buenas prácticas de seguridad</h2>
        <ul class="field-hint">
            <li>Use una contraseña larga y exclusiva; cámbiela periódicamente.</li>
            <li>No comparta la sesión; cierre con <strong>Cerrar sesión</strong> al terminar.</li>
            <li>Limite el rol superusuario: idealmente una o dos cuentas de respaldo.</li>
            <li>Revise periódicamente <a href="{{ route('admin.auditoria.index') }}">Auditoría</a> (IP y acciones de todas las cuentas).</li>
            <li>En producción: HTTPS obligatorio, copias de respaldo y variables <code>SUPERUSER_*</code> solo en el servidor.</li>
        </ul>
    </article>
</section>
@endsection
