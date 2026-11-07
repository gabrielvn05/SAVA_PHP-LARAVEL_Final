@extends('layouts.app')

@section('title', 'Auditoría')

@section('content')
<section class="stack">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Auditoría</h1>
            <p class="page-header__subtitle">
                Registro de acciones de todas las cuentas.
                @if($total === 1)
                    Hay 1 evento en el sistema.
                @else
                    Hay {{ number_format($total, 0, ',', '.') }} eventos en el sistema.
                @endif
            </p>
        </div>
    </header>

    <article class="card stack">
        <form method="GET" action="{{ route('admin.auditoria.index') }}" class="stack">
            <div class="field">
                <label class="field__label" for="auditoria-usuario">Usuario</label>
                @include('partials.user-search', [
                    'id' => 'auditoria-usuario',
                    'name' => 'usuario',
                    'people' => $usuarios,
                    'selected' => $filtros['usuario'] ?? '',
                    'allowAll' => true,
                    'allLabel' => 'Todos los usuarios',
                    'placeholder' => 'Todos, o busque por nombre, correo o cédula…',
                ])
            </div>
            <div class="filter-bar">
            <label class="field">
                <span class="field__label">Acción</span>
                <select name="accion" class="field__input">
                    <option value="">Todas</option>
                    @foreach($acciones as $accion)
                        <option value="{{ $accion }}" @selected(($filtros['accion'] ?? '') === $accion)>
                            {{ \App\Support\AuditLogPresenter::accionLabel($accion) }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span class="field__label">Módulo</span>
                <select name="tabla" class="field__input">
                    <option value="">Todos</option>
                    @foreach($tablas as $tabla)
                        <option value="{{ $tabla }}" @selected(($filtros['tabla'] ?? '') === $tabla)>
                            {{ \App\Support\AuditLogPresenter::tablaLabel($tabla) }}
                        </option>
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
            <label class="field filter-bar__wide">
                <span class="field__label">Buscar en el registro</span>
                <input type="search" name="q" class="field__input" value="{{ $filtros['q'] ?? '' }}" placeholder="IP o identificador del registro">
            </label>
            <button type="submit" class="btn btn--secondary btn--sm">Filtrar</button>
            @if(collect($filtros)->filter(fn ($valor) => filled($valor))->isNotEmpty())
                <a href="{{ route('admin.auditoria.index') }}" class="btn btn--ghost btn--sm">Limpiar</a>
            @endif
            </div>
        </form>

        @if($logs->isEmpty())
            <p class="field-hint" style="margin: 0;">No hay registros con los filtros seleccionados.</p>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Acción</th>
                            <th>Módulo</th>
                            <th>Detalle</th>
                            <th>IP</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                            <tr>
                                <td data-label="Fecha">{{ $log->changed_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                                <td data-label="Usuario">
                                    @if($log->changedBy)
                                        <span>{{ $log->changedBy->nombreCompleto() }}</span>
                                        <span class="field-hint" style="display: block;">{{ $log->changedBy->rol->label() }} · {{ $log->changedBy->email }}</span>
                                    @else
                                        Usuario eliminado
                                    @endif
                                </td>
                                <td data-label="Acción">
                                    <span class="badge {{ \App\Support\AuditLogPresenter::accionBadge($log->action) }}">
                                        {{ \App\Support\AuditLogPresenter::accionLabel($log->action) }}
                                    </span>
                                </td>
                                <td data-label="Módulo">{{ \App\Support\AuditLogPresenter::tablaLabel($log->table_name) }}</td>
                                <td data-label="Detalle">{{ \App\Support\AuditLogPresenter::resumen($log->old_data, $log->new_data, $log->action) }}</td>
                                <td data-label="IP">{{ $log->ip_address ?: '—' }}</td>
                                <td data-label="Acciones">
                                    <a href="{{ route('admin.auditoria.show', $log) }}" class="btn btn--secondary btn--sm">Ver</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $logs->links() }}
        @endif
    </article>
</section>
@endsection
