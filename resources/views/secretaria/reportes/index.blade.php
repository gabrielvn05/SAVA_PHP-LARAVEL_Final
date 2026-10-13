@extends('layouts.app')

@section('title', 'Reportes')

@section('content')
<section class="stack">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Reportes de solicitudes</h1>
        </div>
    </header>

    <article class="card stack">
        <form method="GET" action="{{ route('reportes.index') }}" class="filter-bar">
            <label class="field">
                <span class="field__label">Tipo</span>
                <select name="tipo" class="field__input">
                    <option value="">Todos</option>
                    @foreach($tipos as $tipo)
                        <option value="{{ $tipo->value }}" @selected(($filtros['tipo'] ?? '') === $tipo->value)>{{ $tipo->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span class="field__label">Estado</span>
                <select name="estado" class="field__input">
                    <option value="">Todos</option>
                    @foreach($estados as $estado)
                        <option value="{{ $estado->value }}" @selected(($filtros['estado'] ?? '') === $estado->value)>{{ $estado->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span class="field__label">Desde</span>
                <input type="date" name="desde" class="field__input" value="{{ $filtros['desde'] ?? '' }}">
            </label>
            <label class="field">
                <span class="field__label">Hasta</span>
                <input type="date" name="hasta" class="field__input" value="{{ $filtros['hasta'] ?? '' }}">
            </label>
            <label class="field">
                <span class="field__label">Buscar</span>
                <input type="search" name="q" class="field__input" value="{{ $filtros['q'] ?? '' }}" placeholder="Nombre, correo, motivo…">
            </label>
            <button type="submit" class="btn btn--secondary btn--sm">Filtrar</button>
            <a href="{{ route('reportes.export', request()->query()) }}" class="btn btn--primary btn--sm">Exportar CSV</a>
        </form>

        @if($solicitudes->isEmpty())
            <p class="field-hint">No hay registros con los filtros seleccionados.</p>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Solicitante</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Periodo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($solicitudes as $solicitud)
                            <tr>
                                <td>{{ $solicitud->detalle['codigo_tramite'] ?? '—' }}</td>
                                <td>{{ $solicitud->creador?->nombreCompleto() }}</td>
                                <td>{{ $solicitud->tipo->label() }}</td>
                                <td><span class="badge {{ $solicitud->estado->badgeClass() }}">{{ $solicitud->estado->label() }}</span></td>
                                <td>{{ $solicitud->fecha_inicio->format('d/m/Y') }} – {{ $solicitud->fecha_fin->format('d/m/Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="field-hint">Mostrando hasta 50 registros. Exporta CSV para el listado completo.</p>
        @endif
    </article>
</section>
@endsection
