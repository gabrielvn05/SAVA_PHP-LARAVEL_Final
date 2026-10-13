@extends('layouts.app')

@section('title', 'Mis solicitudes')

@section('content')
<section class="stack">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Mis solicitudes</h1>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('solicitudes.create') }}" class="btn btn--secondary btn--sm">Formulario simple</a>
            <a href="{{ route('solicitudes.wizard') }}" class="btn btn--primary">Nueva solicitud</a>
        </div>
    </header>

    <article class="card" style="margin-bottom: 1rem;">
        <h2 class="page-header__title" style="font-size: 1rem; margin: 0 0 0.75rem;">Filtros</h2>
        <form method="GET" action="{{ route('solicitudes.index') }}" class="solicitud-filters" data-no-loading>
            <div>
                <label for="f-estado">Estado del trámite</label>
                <select id="f-estado" name="estado">
                    <option value="">Todos los procesos</option>
                    @foreach($estados as $estado)
                        <option value="{{ $estado->value }}" @selected(($filtros['estado'] ?? '') === $estado->value)>{{ $estado->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-tipo">Tipo</label>
                <select id="f-tipo" name="tipo">
                    <option value="">Todos</option>
                    @foreach($tipos as $tipo)
                        <option value="{{ $tipo->value }}" @selected(($filtros['tipo'] ?? '') === $tipo->value)>{{ $tipo->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="solicitud-filters__span2">
                <label for="f-q">Nombre o texto en motivo</label>
                <input id="f-q" type="search" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="Buscar...">
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
                        <th>Tipo</th>
                        <th>Periodo</th>
                        <th>Estado</th>
                        <th>Motivo</th>
                        <th>Carrera</th>
                        <th>Rol</th>
                        <th>Justificativo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($solicitudes as $solicitud)
                        <tr>
                            <td>{{ $solicitud->tipo->label() }}</td>
                            <td>{{ $solicitud->fecha_inicio->format('Y-m-d') }} - {{ $solicitud->fecha_fin->format('Y-m-d') }}</td>
                            <td><span class="badge {{ $solicitud->estado->badgeClass() }}">{{ $solicitud->estado->label() }}</span></td>
                            <td>{{ \Illuminate\Support\Str::limit($solicitud->motivo, 80) }}</td>
                            <td>{{ \App\Support\Carreras::label($solicitud->creador->carrera ?? '') }}</td>
                            <td>{{ $solicitud->creador->rol->label() }}</td>
                            <td>{{ $solicitud->justificativo_nombre ?: '—' }}</td>
                            <td><a href="{{ route('solicitudes.show', $solicitud) }}">Ver</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--color-text-muted);">
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
