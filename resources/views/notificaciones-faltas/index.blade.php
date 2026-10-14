@extends('layouts.app')

@section('title', 'Notificaciones de faltas')

@section('content')
<section class="stack notif-th-page">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Notificaciones de faltas</h1>
            <p class="page-header__subtitle">
                Oficios informativos por datos de Talento Humano (marcación Face ID). El Decano firma por defecto;
                la secretaría delegada puede emitirlos como Decano (s).
            </p>
        </div>
    </header>

    <div class="dash-metrics">
        <article class="card dash-metric dash-metric--primary">
            <span class="dash-metric__label">Registradas</span>
            <div class="dash-metric__row">
                <strong class="dash-metric__value">{{ $stats['total'] }}</strong>
            </div>
            <p class="dash-metric__hint">Total de notificaciones en SAVA</p>
        </article>
        <article class="card dash-metric dash-metric--warning">
            <span class="dash-metric__label">Sin justificar</span>
            <div class="dash-metric__row">
                <strong class="dash-metric__value">{{ $stats['pendientes'] }}</strong>
            </div>
            <p class="dash-metric__hint">Colaboradores aún sin solicitud aprobada</p>
        </article>
        <article class="card dash-metric dash-metric--success">
            <span class="dash-metric__label">Ya justificaron</span>
            <div class="dash-metric__row">
                <strong class="dash-metric__value">{{ $stats['justificadas'] }}</strong>
            </div>
            <p class="dash-metric__hint">Coinciden con trámite aprobado en la fecha</p>
        </article>
    </div>

    <div class="notif-th-grid">
        <div class="notif-th-sidebar stack">
            <article class="card card-section">
                <div class="card-section__head">
                    <h2 class="card-section__title">Registro manual</h2>
                    <p class="card-section__desc">Un colaborador y una fecha de falta de marcación.</p>
                </div>
                <form method="POST" action="{{ route('notificaciones-faltas.store') }}" class="card-section__body stack">
                    @csrf
                    <label class="field">
                        <span class="field__label">Usuario notificado (recibe el correo de aviso)</span>
                        <select name="destinatario_id" class="field__input" required id="notif-th-colaborador-select">
                            <option value="">Seleccione…</option>
                            @foreach($colaboradores as $persona)
                                <option
                                    value="{{ $persona->id }}"
                                    data-email="{{ $persona->email }}"
                                    @selected(old('destinatario_id') === $persona->id)
                                >
                                    {{ $persona->nombreCompleto() }}
                                    · {{ $persona->cedula ?: 'sin cédula' }}
                                    · {{ $persona->rol->label() }}
                                    @unless($persona->activo) (inactivo) @endunless
                                </option>
                            @endforeach
                        </select>
                        <p class="field-hint" id="notif-th-colaborador-email" style="margin-top: 0.35rem;">
                            El aviso se enviará al correo del colaborador seleccionado.
                        </p>
                    </label>
                    <script>
                        (function () {
                            var sel = document.getElementById('notif-th-colaborador-select');
                            var hint = document.getElementById('notif-th-colaborador-email');
                            if (!sel || !hint) return;
                            function sync() {
                                var opt = sel.options[sel.selectedIndex];
                                var email = opt && opt.dataset ? opt.dataset.email : '';
                                hint.textContent = email
                                    ? 'Correo de aviso: ' + email
                                    : 'El aviso se enviará al correo del colaborador seleccionado.';
                            }
                            sel.addEventListener('change', sync);
                            sync();
                        })();
                    </script>
                    <div class="form-grid form-grid--2">
                        <label class="field">
                            <span class="field__label">Fecha sin marcación</span>
                            <input type="date" name="fecha_falta" class="field__input" value="{{ old('fecha_falta') }}" required>
                        </label>
                        <label class="field">
                            <span class="field__label">Marcación faltante</span>
                            <select name="tipo_marcacion" class="field__input" required>
                                @foreach($tiposMarcacion as $tipo)
                                    <option value="{{ $tipo }}" @selected(old('tipo_marcacion') === $tipo)>
                                        {{ \App\Support\NotificacionFaltaMarcacion::label($tipo) }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <label class="field">
                        <span class="field__label">Notas internas (opcional)</span>
                        <textarea name="notas" class="field__input" rows="2" maxlength="2000" placeholder="Referencia del informe TH…">{{ old('notas') }}</textarea>
                    </label>
                    <button type="submit" class="btn btn--primary btn--block">Registrar y asignar código</button>
                </form>
            </article>

            <article class="card card-section card-section--muted">
                <div class="card-section__head">
                    <h2 class="card-section__title">Importar CSV</h2>
                    <p class="card-section__desc">Listado enviado por Talento Humano.</p>
                </div>
                <div class="card-section__body stack">
                    <div class="notif-th-csv-hint">
                        <p><strong>Columnas:</strong> <code>cedula</code>, <code>fecha</code>, <code>tipo_marcacion</code></p>
                        <p class="field-hint">Fecha <code>YYYY-MM-DD</code>. Tipo: <code>entrada</code>, <code>salida</code> o <code>entrada_salida</code>.</p>
                    </div>
                    <form method="POST" action="{{ route('notificaciones-faltas.import') }}" enctype="multipart/form-data" class="stack">
                        @csrf
                        <label class="field">
                            <span class="field__label">Archivo</span>
                            <input type="file" name="archivo_csv" class="field__input notif-th-file" accept=".csv,text/csv" required>
                        </label>
                        <button type="submit" class="btn btn--secondary btn--block">Importar filas</button>
                    </form>
                </div>
            </article>
        </div>

        <article class="card card--flat notif-th-history">
            <div class="notif-th-history__head">
                <div>
                    <h2 class="card-section__title">Historial reciente</h2>
                    <p class="field-hint" style="margin: 0.25rem 0 0;">Últimas 100 notificaciones · ordenadas por fecha de registro</p>
                </div>
                <form method="GET" action="{{ route('notificaciones-faltas.index') }}" class="notif-th-search" data-no-loading>
                    <label class="field" style="margin: 0;">
                        <span class="visually-hidden">Buscar colaborador</span>
                        <input
                            type="search"
                            name="q"
                            class="field__input field__input--sm"
                            value="{{ $filtroQ }}"
                            placeholder="Buscar por nombre, cédula o correo…"
                        >
                    </label>
                    <button type="submit" class="btn btn--secondary btn--sm">Buscar</button>
                    @if($filtroQ !== '')
                        <a href="{{ route('notificaciones-faltas.index') }}" class="btn btn--ghost btn--sm">Limpiar</a>
                    @endif
                </form>
            </div>

            @if($notificaciones->isEmpty())
                <div class="notif-th-empty">
                    <p class="notif-th-empty__title">No hay notificaciones</p>
                    <p class="field-hint">Registre la primera manualmente o importe un CSV de Talento Humano.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="data-table data-table--compact">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Colaborador</th>
                                <th>Fecha</th>
                                <th>Marcación</th>
                                <th>Estado</th>
                                <th>Emisión</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($notificaciones as $item)
                                <tr>
                                    <td data-label="Código"><span class="text-code">{{ $item->codigo_oficio }}</span></td>
                                    <td data-label="Colaborador">
                                        <span class="notif-th-person">{{ $item->destinatario?->nombreCompleto() }}</span>
                                        @if($item->destinatario?->cedula)
                                            <span class="field-hint notif-th-person__meta">{{ $item->destinatario->cedula }}</span>
                                        @endif
                                    </td>
                                    <td data-label="Fecha">{{ $item->fecha_falta->format('d/m/Y') }}</td>
                                    <td data-label="Marcación">{{ \App\Support\NotificacionFaltaMarcacion::label($item->tipo_marcacion) }}</td>
                                    <td data-label="Estado">
                                        @if($item->tiene_justificacion_aprobada)
                                            <span class="badge badge--success">Justificado</span>
                                        @else
                                            <span class="badge badge--warning">Pendiente</span>
                                        @endif
                                    </td>
                                    <td data-label="Emisión">
                                        <span class="text-truncate">{{ $item->emitidoPor?->nombreCompleto() }}</span>
                                        @if($item->emitido_delegado)
                                            <span class="badge badge--muted badge--xs">Delegado</span>
                                        @endif
                                    </td>
                                    <td data-label="Acciones">
                                        <div class="cell-actions cell-actions--inline">
                                            <a href="{{ route('notificaciones-faltas.show', $item) }}" class="btn btn--secondary btn--sm">Detalle</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </article>
    </div>
</section>
@endsection
