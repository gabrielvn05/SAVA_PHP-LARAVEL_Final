@extends('layouts.app')

@section('title', 'Proceso de aprobación')

@section('content')
<section class="stack">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Proceso de aprobación</h1>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('solicitudes.index') }}" class="btn btn--secondary btn--sm">Mis solicitudes</a>
            <a href="{{ route('solicitudes.wizard') }}" class="btn btn--primary btn--sm">Nueva solicitud</a>
        </div>
    </header>

    <article class="card" style="margin-bottom: 1rem;">
        <h2 class="page-header__title" style="font-size: 1rem; margin: 0 0 0.75rem;">Filtros</h2>
        <form method="GET" action="{{ route('solicitudes.proceso') }}" class="solicitud-filters" data-no-loading>
            <div>
                <label for="pf-nombre">Nombre solicitante</label>
                <input id="pf-nombre" type="search" name="nombre" value="{{ $filtros['nombre'] ?? '' }}" placeholder="Apellido o nombre…">
            </div>
            <div>
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
            <div>
                <label for="pf-carrera">Carrera</label>
                <select id="pf-carrera" name="carrera">
                    <option value="">Todas</option>
                    @foreach($carreras as $carrera)
                        <option value="{{ $carrera['value'] }}" @selected(($filtros['carrera'] ?? '') === $carrera['value'])>{{ $carrera['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="pf-desde">Fecha desde</label>
                <input id="pf-desde" type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}">
            </div>
            <div>
                <label for="pf-hasta">Fecha hasta</label>
                <input id="pf-hasta" type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}">
            </div>
            <div class="solicitud-filters__span2">
                <label for="pf-estado">Proceso / estado</label>
                <select id="pf-estado" name="estado">
                    <option value="">Todos los procesos</option>
                    @foreach($estados as $estado)
                        <option value="{{ $estado->value }}" @selected(($filtros['estado'] ?? '') === $estado->value)>{{ $estado->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn--secondary btn--sm">Filtrar</button>
            </div>
        </form>
        <p class="field-hint" style="margin-top: 0.75rem; margin-bottom: 0;">
            {{ $solicitudes->total() }} solicitud{{ $solicitudes->total() === 1 ? '' : 'es' }} encontrada{{ $solicitudes->total() === 1 ? '' : 's' }}.
            Se muestran 10 por página.
        </p>
    </article>

    <article class="card card--flat">
        <div class="table-wrap">
            <table class="data-table data-table--compact">
                <thead>
                    <tr>
                        <th>Solicitante</th>
                        <th>Tipo</th>
                        <th>Periodo</th>
                        <th>Estado</th>
                        <th>Carrera</th>
                        <th>Rol</th>
                        <th>Motivo</th>
                        <th>Justificativo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($solicitudes as $solicitud)
                        <tr>
                            <td><span class="text-truncate">{{ $solicitud->creador->nombreCompleto() }}</span></td>
                            <td>{{ $solicitud->tipo->label() }}</td>
                            <td>{{ $solicitud->fecha_inicio->format('Y-m-d') }} - {{ $solicitud->fecha_fin->format('Y-m-d') }}</td>
                            <td><span class="badge {{ $solicitud->estado->badgeClass() }}">{{ $solicitud->estado->label() }}</span></td>
                            <td><span class="text-truncate">{{ \App\Support\Carreras::label($solicitud->creador->carrera ?? '') }}</span></td>
                            <td>{{ $solicitud->creador->rol->label() }}</td>
                            <td><span class="text-truncate">{{ \Illuminate\Support\Str::limit($solicitud->motivo, 70) }}</span></td>
                            <td><span class="text-truncate">{{ $solicitud->justificativo_nombre ?: '—' }}</span></td>
                            <td>
                                <div class="cell-actions">
                                    <a href="{{ route('solicitudes.show', $solicitud) }}" class="btn btn--secondary btn--sm">Ver detalles</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align: center; color: var(--color-text-muted);">
                                No hay solicitudes con estos filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($solicitudes->hasPages())
            <div style="padding: 0.75rem;">{{ $solicitudes->links() }}</div>
        @endif
    </article>
</section>
@endsection
