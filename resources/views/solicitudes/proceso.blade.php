@extends('layouts.app')

@section('title', 'Proceso de aprobación')

@php
    $hayFiltros = collect($filtros ?? [])->contains(fn ($valor) => filled($valor));
    $total = $solicitudes->total();
@endphp

@section('content')
<section class="stack">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Proceso de aprobación</h1>
            <p class="page-header__subtitle">
                {{ $total }} {{ $total === 1 ? 'solicitud' : 'solicitudes' }}{{ $hayFiltros ? ' con estos filtros' : '' }}.
            </p>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('solicitudes.index') }}" class="btn btn--secondary btn--sm">Mis solicitudes</a>
            <a href="{{ route('solicitudes.wizard') }}" class="btn btn--primary btn--sm">Nueva solicitud</a>
        </div>
    </header>

    <form method="GET" action="{{ route('solicitudes.proceso') }}" class="list-toolbar" data-no-loading>
            <div class="list-toolbar__field list-toolbar__field--grow">
                <label for="pf-nombre">Nombre solicitante</label>
                <input id="pf-nombre" type="search" name="nombre" value="{{ $filtros['nombre'] ?? '' }}" placeholder="Apellido o nombre">
            </div>
            <div class="list-toolbar__field">
                <label for="pf-rol">Rol</label>
                <select id="pf-rol" name="rol">
                    <option value="">Todos</option>
                    @foreach($roles as $rol)
                        @if($rol !== \App\Enums\AppRole::Superusuario)
                            <option value="{{ $rol->value }}" @selected(($filtros['rol'] ?? '') === $rol->value)>{{ $rol->label() }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="list-toolbar__field">
                <label for="pf-carrera">Carrera</label>
                <select id="pf-carrera" name="carrera">
                    <option value="">Todas</option>
                    @foreach($carreras as $carrera)
                        <option value="{{ $carrera['value'] }}" @selected(($filtros['carrera'] ?? '') === $carrera['value'])>{{ $carrera['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="list-toolbar__field">
                <label for="pf-desde">Fecha desde</label>
                <input id="pf-desde" type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}">
            </div>
            <div class="list-toolbar__field">
                <label for="pf-hasta">Fecha hasta</label>
                <input id="pf-hasta" type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}">
            </div>
            <div class="list-toolbar__field">
                <label for="pf-estado">Estado</label>
                <select id="pf-estado" name="estado">
                    <option value="">Todos los procesos</option>
                    @foreach($estados as $estado)
                        <option value="{{ $estado->value }}" @selected(($filtros['estado'] ?? '') === $estado->value)>{{ $estado->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="list-toolbar__actions">
                <button type="submit" class="btn btn--secondary btn--sm">Filtrar</button>
                @if($hayFiltros)
                    <a href="{{ route('solicitudes.proceso') }}" class="btn btn--ghost btn--sm">Limpiar</a>
                @endif
            </div>
    </form>

    @if($solicitudes->isEmpty())
        <div class="list-empty">
            @if($hayFiltros)
                <h2 class="list-empty__title">Ninguna solicitud coincide</h2>
                <p class="list-empty__text">Prueba con otro solicitante, estado o fecha, o quita los filtros para ver la bandeja completa.</p>
                <div class="list-empty__actions">
                    <a href="{{ route('solicitudes.proceso') }}" class="btn btn--secondary btn--sm">Quitar filtros</a>
                </div>
            @else
                <h2 class="list-empty__title">No hay solicitudes en esta bandeja</h2>
                <p class="list-empty__text">Cuando alguien envíe un trámite, aparecerá aquí para revisarlo.</p>
            @endif
        </div>
    @else
    <article class="card card--flat">
        <div class="table-wrap">
            <table class="data-table data-table--compact data-table--fit">
                <thead>
                    <tr>
                        <th>Solicitante</th>
                        <th>Tipo</th>
                        <th>Periodo</th>
                        <th>Estado</th>
                        <th>Motivo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($solicitudes as $solicitud)
                        @php
                            $carreraLista = \App\Support\Carreras::label($solicitud->creador->carrera ?? '');
                            $metaSolicitante = collect([$carreraLista !== '—' ? $carreraLista : null, $solicitud->creador->rol->label()])
                                ->filter()
                                ->implode(' · ');
                        @endphp
                        <tr>
                            <td data-label="Solicitante">
                                <span class="list-person">
                                    <span class="list-person__name">{{ $solicitud->creador->nombreCompleto() }}</span>
                                    <span class="list-person__meta">{{ $metaSolicitante }}</span>
                                </span>
                            </td>
                            <td data-label="Tipo">{{ $solicitud->tipo->label() }}</td>
                            <td data-label="Periodo">{{ \App\Support\SolicitudPeriodo::label($solicitud) }}</td>
                            <td data-label="Estado"><span class="badge {{ $solicitud->estado->badgeClass() }}">{{ $solicitud->estado->label() }}</span></td>
                            <td data-label="Motivo" title="{{ $solicitud->motivo }}">{{ \Illuminate\Support\Str::limit($solicitud->motivo, 90) }}</td>
                            <td data-label="Acciones">
                                <a href="{{ route('solicitudes.show', $solicitud) }}" class="btn btn--secondary btn--sm">Ver</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $solicitudes->links() }}
    </article>
    @endif
</section>
@endsection
