@extends('layouts.app')

@section('title', 'Mis solicitudes')

@php
    $hayFiltros = collect($filtros ?? [])->contains(fn ($valor) => filled($valor));
    $total = $solicitudes->total();
@endphp

@section('content')
<section class="stack">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Mis solicitudes</h1>
            <p class="page-header__subtitle">
                {{ $total }} {{ $total === 1 ? 'solicitud' : 'solicitudes' }}{{ $hayFiltros ? ' con estos filtros' : '' }}.
            </p>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('solicitudes.create') }}" class="btn btn--secondary btn--sm">Formulario simple</a>
            <a href="{{ route('solicitudes.wizard') }}" class="btn btn--primary">Nueva solicitud</a>
        </div>
    </header>

    <form method="GET" action="{{ route('solicitudes.index') }}" class="list-toolbar" data-no-loading>
        <div class="list-toolbar__field">
            <label for="f-estado">Estado</label>
            <select id="f-estado" name="estado">
                <option value="">Todos</option>
                @foreach($estados as $estado)
                    <option value="{{ $estado->value }}" @selected(($filtros['estado'] ?? '') === $estado->value)>{{ $estado->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="list-toolbar__field">
            <label for="f-tipo">Tipo</label>
            <select id="f-tipo" name="tipo">
                <option value="">Todos</option>
                @foreach($tipos as $tipo)
                    <option value="{{ $tipo->value }}" @selected(($filtros['tipo'] ?? '') === $tipo->value)>{{ $tipo->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="list-toolbar__field list-toolbar__field--grow">
            <label for="f-q">Buscar en el motivo</label>
            <input id="f-q" type="search" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="Texto del motivo">
        </div>
        <div class="list-toolbar__actions">
            <button type="submit" class="btn btn--secondary btn--sm">Filtrar</button>
            @if($hayFiltros)
                <a href="{{ route('solicitudes.index') }}" class="btn btn--ghost btn--sm">Limpiar</a>
            @endif
        </div>
    </form>

    @if($solicitudes->isEmpty())
        <div class="list-empty">
            @if($hayFiltros)
                <h2 class="list-empty__title">Ninguna solicitud coincide</h2>
                <p class="list-empty__text">Prueba con otro estado, tipo o texto, o quita los filtros para ver el listado completo.</p>
                <div class="list-empty__actions">
                    <a href="{{ route('solicitudes.index') }}" class="btn btn--secondary btn--sm">Quitar filtros</a>
                </div>
            @else
                <h2 class="list-empty__title">Aún no tienes solicitudes</h2>
                <p class="list-empty__text">Cuando registres un permiso o una justificación, aparecerá en esta lista.</p>
                <div class="list-empty__actions">
                    <a href="{{ route('solicitudes.wizard') }}" class="btn btn--primary btn--sm">Nueva solicitud</a>
                </div>
            @endif
        </div>
    @else
        <article class="card card--flat">
            <div class="table-wrap">
                <table class="data-table data-table--compact data-table--fit">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Periodo</th>
                            <th>Estado</th>
                            <th>Motivo</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($solicitudes as $solicitud)
                            <tr>
                                <td data-label="Tipo">{{ $solicitud->tipo->label() }}</td>
                                <td data-label="Periodo">{{ \App\Support\SolicitudPeriodo::label($solicitud) }}</td>
                                <td data-label="Estado"><span class="badge {{ $solicitud->estado->badgeClass() }}">{{ $solicitud->estado->label() }}</span></td>
                                <td data-label="Motivo" title="{{ $solicitud->motivo }}">{{ \Illuminate\Support\Str::limit($solicitud->motivo, 90) }}</td>
                                <td data-label="Acciones"><a href="{{ route('solicitudes.show', $solicitud) }}" class="btn btn--secondary btn--sm">Ver</a></td>
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
