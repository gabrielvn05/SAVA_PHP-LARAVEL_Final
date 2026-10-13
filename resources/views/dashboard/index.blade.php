@extends('layouts.app')

@section('title', 'Panel')

@php
    $pct = fn (int $n): float => $stats['total'] > 0 ? round(($n / $stats['total']) * 1000) / 10 : 0;
    $radius = 42;
    $circumference = 2 * M_PI * $radius;
    $donutOffset = 0;
    $donutTotal = collect($stats['byEstado'])->sum('count');
@endphp

@section('content')
<section class="stack dashboard-page">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Hola, {{ $user->nombres }}</h1>
        </div>
    </header>

    <div class="dash-metrics">
        <article class="card dash-metric dash-metric--primary">
            <span class="dash-metric__label">Total solicitudes</span>
            <div class="dash-metric__row">
                <strong class="dash-metric__value">{{ $stats['total'] }}</strong>
            </div>
            <p class="dash-metric__hint">Alcance: {{ $stats['scopeLabel'] }}</p>
        </article>
        <article class="card dash-metric dash-metric--warning">
            <span class="dash-metric__label">En proceso</span>
            <div class="dash-metric__row">
                <strong class="dash-metric__value">{{ $stats['enProceso'] }}</strong>
                <span class="dash-metric__pct">{{ $pct($stats['enProceso']) }}%</span>
            </div>
            <p class="dash-metric__hint">Borrador, revisión o firma pendiente</p>
        </article>
        <article class="card dash-metric dash-metric--success">
            <span class="dash-metric__label">Aprobadas</span>
            <div class="dash-metric__row">
                <strong class="dash-metric__value">{{ $stats['aprobadas'] }}</strong>
                <span class="dash-metric__pct">{{ $pct($stats['aprobadas']) }}%</span>
            </div>
            <p class="dash-metric__hint">Tasa de aprobación: {{ $stats['tasaAprobacion'] }}%</p>
        </article>
        <article class="card dash-metric dash-metric--info">
            <span class="dash-metric__label">Últimos 30 días</span>
            <div class="dash-metric__row">
                <strong class="dash-metric__value">{{ $stats['recientes30Dias'] }}</strong>
                <span class="dash-metric__pct">{{ $pct($stats['recientes30Dias']) }}%</span>
            </div>
            <p class="dash-metric__hint">Resueltas: {{ $stats['tasaResolucion'] }}% del total</p>
        </article>
    </div>

    <div class="dash-charts">
        <article class="card dash-chart-card">
            <div class="dash-chart-card__head">
                <h2>Distribución por estado</h2>
                <p class="field-hint">Porcentaje de cada etapa del flujo.</p>
            </div>
            <div class="dash-chart-card__body dash-chart-card__body--split">
                <div class="dash-donut {{ $donutTotal === 0 ? 'dash-donut--empty' : '' }}">
                    <svg viewBox="0 0 120 120" aria-hidden="true">
                        <circle cx="60" cy="60" r="{{ $radius }}" fill="none" stroke="{{ $donutTotal === 0 ? '#e2e8f0' : '#eef2f7' }}" stroke-width="14"></circle>
                        @if($donutTotal > 0)
                            @foreach($stats['byEstado'] as $segment)
                                @php
                                    $length = ($segment['count'] / $donutTotal) * $circumference;
                                    $dashArray = $length.' '.($circumference - $length);
                                    $dashOffset = -$donutOffset;
                                    $donutOffset += $length;
                                @endphp
                                <circle
                                    cx="60" cy="60" r="{{ $radius }}"
                                    fill="none"
                                    stroke="{{ $segment['color'] }}"
                                    stroke-width="14"
                                    stroke-dasharray="{{ $dashArray }}"
                                    stroke-dashoffset="{{ $dashOffset }}"
                                    transform="rotate(-90 60 60)"
                                ></circle>
                            @endforeach
                        @endif
                    </svg>
                    <div class="dash-donut__center">
                        <strong>{{ $donutTotal }}</strong>
                        <span>{{ $donutTotal === 0 ? 'Sin datos' : $stats['scopeLabel'] }}</span>
                    </div>
                </div>
                <div class="dash-legend">
                    @forelse($stats['byEstado'] as $segment)
                        <div class="dash-legend__item">
                            <span class="dash-legend__swatch" style="background-color: {{ $segment['color'] }}"></span>
                            <span class="dash-legend__label">{{ $segment['label'] }}</span>
                            <span class="dash-legend__value">{{ $segment['count'] }} ({{ $segment['pct'] }}%)</span>
                        </div>
                    @empty
                        <p class="field-hint dash-chart-empty">Aún no hay solicitudes registradas.</p>
                    @endforelse
                </div>
            </div>
        </article>

        <article class="card dash-chart-card">
            <div class="dash-chart-card__head">
                <h2>Tipos de trámite</h2>
                <p class="field-hint">Composición por categoría de solicitud.</p>
            </div>
            @if(empty($stats['byTipo']))
                <p class="field-hint dash-chart-empty">No hay datos para mostrar.</p>
            @else
                @php $maxTipo = max(array_column($stats['byTipo'], 'count') ?: [1]); @endphp
                <div class="dash-bars">
                    @foreach($stats['byTipo'] as $segment)
                        <div class="dash-bar">
                            <div class="dash-bar__head">
                                <span class="dash-bar__label">{{ $segment['label'] }}</span>
                                <span class="dash-bar__meta">{{ $segment['count'] }} · {{ $segment['pct'] }}%</span>
                            </div>
                            <div class="dash-bar__track">
                                <div class="dash-bar__fill" style="width: {{ ($segment['count'] / $maxTipo) * 100 }}%; background-color: {{ $segment['color'] }};"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>
    </div>

    <div class="dashboard-grid">
        <article class="card dashboard-tile stack">
            <h2 style="margin: 0;">Mis solicitudes</h2>
            <p class="field-hint">Consulta, crea y edita tus solicitudes y certificados.</p>
            <div class="row" style="gap: 8px; flex-wrap: wrap;">
                <a href="{{ route('solicitudes.index') }}" class="btn btn--secondary btn--sm" style="width: fit-content;">Ver mis solicitudes</a>
                <a href="{{ route('solicitudes.wizard') }}" class="btn btn--primary btn--sm" style="width: fit-content;">Nueva solicitud</a>
            </div>
        </article>

        @if(! $user->isSolicitante() && $user->hasCapability(\App\Enums\CapabilityType::RevisarSolicitudes))
            <article class="card dashboard-tile stack" style="border-left-color: var(--color-warning);">
                <h2 style="margin: 0;">Revisión (Secretaría)</h2>
                <p class="field-hint">
                    Pendientes: <strong>{{ $stats['pendientesRevision'] }}</strong>
                    @if($stats['total'] > 0)
                        · {{ $pct($stats['pendientesRevision']) }}% del total
                    @endif
                </p>
                <a href="{{ route('solicitudes.proceso') }}" class="btn btn--secondary btn--sm" style="width: fit-content;">Abrir proceso</a>
            </article>
        @endif

        @if(! $user->isSolicitante() && $user->hasCapability(\App\Enums\CapabilityType::AprobarSolicitudes))
            <article class="card dashboard-tile stack" style="border-left-color: var(--color-success);">
                <h2 style="margin: 0;">Aprobación y firma</h2>
                <p class="field-hint">
                    Pendientes de firma: <strong>{{ $stats['pendientesFirma'] }}</strong>
                    @if($stats['total'] > 0)
                        · {{ $pct($stats['pendientesFirma']) }}% del total
                    @endif
                </p>
                <a href="{{ route('solicitudes.proceso') }}" class="btn btn--secondary btn--sm" style="width: fit-content;">Atender pendientes</a>
            </article>
        @endif

        @if($user->rol === \App\Enums\AppRole::Decano)
            <article class="card dashboard-tile stack" style="border-left-color: var(--color-accent);">
                <h2 style="margin: 0;">Usuarios</h2>
                <p class="field-hint">Cambia el rol de docentes, administrativos y Secretaría.</p>
                <a href="{{ route('admin.usuarios.index') }}" class="btn btn--primary btn--sm" style="width: fit-content;">Gestionar usuarios</a>
            </article>
            <article class="card dashboard-tile stack" style="border-left-color: var(--color-accent);">
                <h2 style="margin: 0;">Solicitudes de cuenta</h2>
                <p class="field-hint">Pendientes por revisar: {{ $stats['solicitudesCuentaPendientes'] ?? 0 }}</p>
                <a href="{{ route('admin.solicitudes-cuenta.index') }}" class="btn btn--primary btn--sm" style="width: fit-content;">Revisar solicitudes</a>
            </article>
            <article class="card dashboard-tile stack" style="border-left-color: var(--color-info);">
                <h2 style="margin: 0;">Reportes</h2>
                <p class="field-hint">Consolidados por tipo de solicitud y periodo.</p>
                <a href="{{ route('reportes.index') }}" class="btn btn--primary btn--sm" style="width: fit-content;">Abrir reportes</a>
            </article>
        @endif

        @if($user->rol === \App\Enums\AppRole::Secretaria)
            <article class="card dashboard-tile stack" style="border-left-color: var(--color-info);">
                <h2 style="margin: 0;">Reportes</h2>
                <p class="field-hint">Consolidados por tipo de solicitud y periodo.</p>
                <a href="{{ route('reportes.index') }}" class="btn btn--primary btn--sm" style="width: fit-content;">Abrir reportes</a>
            </article>
            <article class="card dashboard-tile stack" style="border-left-color: var(--color-warning);">
                <h2 style="margin: 0;">Solicitudes de cuenta (rechazo)</h2>
                <p class="field-hint">Pendientes por resolver: {{ $stats['solicitudesCuentaPendientes'] ?? 0 }}</p>
                <a href="{{ route('admin.solicitudes-cuenta.index') }}" class="btn btn--secondary btn--sm" style="width: fit-content;">Abrir bandeja</a>
            </article>
        @endif

        <article class="card dashboard-tile stack" style="border-left-color: var(--color-danger);">
            <h2 style="margin: 0;">Rechazadas</h2>
            <p class="field-hint">
                Total: <strong>{{ $stats['rechazadas'] }}</strong>
                @if($stats['total'] > 0)
                    · {{ $pct($stats['rechazadas']) }}% del total
                @endif
            </p>
            <a href="{{ route('solicitudes.index', ['estado' => 'rechazada']) }}" class="btn btn--secondary btn--sm" style="width: fit-content;">Ver historial</a>
        </article>
    </div>
</section>
@endsection
