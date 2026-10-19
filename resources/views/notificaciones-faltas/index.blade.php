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
            <article class="card card-section card-section--overflow">
                <div class="card-section__head">
                    <h2 class="card-section__title">Registro manual</h2>
                    <p class="card-section__desc">Un colaborador y una fecha de falta de marcación.</p>
                </div>
                <form method="POST" action="{{ route('notificaciones-faltas.store') }}" class="card-section__body stack" id="notif-th-registro">
                    @csrf
                    @php
                        $personasBusqueda = $colaboradores->map(fn ($persona) => [
                            'id' => $persona->id,
                            'nombre' => $persona->nombreCompleto(),
                            'cedula' => (string) ($persona->cedula ?? ''),
                            'rol' => $persona->rol->label(),
                            'email' => (string) $persona->email,
                            'activo' => (bool) $persona->activo,
                        ])->values();
                    @endphp
                    <div class="field">
                        <label class="field__label" for="notif-th-colaborador-buscar">Usuario notificado (recibe el correo de aviso)</label>
                        <div class="user-search" id="notif-th-colaborador">
                            <input
                                type="text"
                                id="notif-th-colaborador-buscar"
                                class="field__input"
                                placeholder="Buscar por nombre o cédula…"
                                autocomplete="off"
                                role="combobox"
                                aria-autocomplete="list"
                                aria-expanded="false"
                                aria-controls="notif-th-colaborador-lista"
                            >
                            <input type="hidden" name="destinatario_id" id="notif-th-colaborador-id" value="{{ old('destinatario_id') }}">
                            <ul class="user-search__list" id="notif-th-colaborador-lista" role="listbox" hidden></ul>
                        </div>
                        <p class="field-hint" id="notif-th-colaborador-email" style="margin-top: 0.35rem;">
                            Escriba el nombre o la cédula y elija al colaborador.
                        </p>
                    </div>
                    <script>
                        (function () {
                            var root = document.getElementById('notif-th-colaborador');
                            var input = document.getElementById('notif-th-colaborador-buscar');
                            var hidden = document.getElementById('notif-th-colaborador-id');
                            var list = document.getElementById('notif-th-colaborador-lista');
                            var hint = document.getElementById('notif-th-colaborador-email');
                            var form = document.getElementById('notif-th-registro');
                            var personas = @json($personasBusqueda);
                            var activo = -1;
                            var seleccionado = null;

                            if (!root || !input || !hidden || !list || !hint) return;

                            function normalizar(texto) {
                                return (texto || '')
                                    .toLocaleLowerCase('es')
                                    .normalize('NFD')
                                    .replace(/[\u0300-\u036f]/g, '');
                            }

                            function etiqueta(persona) {
                                var cedula = persona.cedula || 'sin cédula';
                                var texto = persona.nombre + ' · ' + cedula + ' · ' + persona.rol;
                                if (!persona.activo) texto += ' (inactivo)';
                                return texto;
                            }

                            function coincide(persona, consulta) {
                                var q = normalizar(consulta).trim();
                                if (!q) return true;
                                var nombre = normalizar(persona.nombre + ' ' + persona.rol);
                                var cedula = (persona.cedula || '').replace(/\D/g, '');
                                var qDigitos = q.replace(/\D/g, '');
                                if (nombre.indexOf(q) !== -1) return true;
                                if (normalizar(persona.cedula).indexOf(q) !== -1) return true;
                                return qDigitos.length > 0 && cedula.indexOf(qDigitos) !== -1;
                            }

                            function pintarCorreo(persona) {
                                hint.textContent = persona && persona.email
                                    ? 'Correo de aviso: ' + persona.email
                                    : 'Escriba el nombre o la cédula y elija al colaborador.';
                            }

                            function cerrar() {
                                list.hidden = true;
                                input.setAttribute('aria-expanded', 'false');
                                activo = -1;
                            }

                            function abrir() {
                                list.hidden = false;
                                input.setAttribute('aria-expanded', 'true');
                            }

                            function elegir(persona) {
                                seleccionado = persona;
                                hidden.value = persona.id;
                                input.value = etiqueta(persona);
                                input.setCustomValidity('');
                                pintarCorreo(persona);
                                cerrar();
                            }

                            function consultaActual() {
                                if (seleccionado && input.value === etiqueta(seleccionado)) return '';
                                return input.value;
                            }

                            function render() {
                                var visibles = personas.filter(function (persona) {
                                    return coincide(persona, consultaActual());
                                }).slice(0, 40);
                                list.innerHTML = '';
                                activo = -1;
                                if (visibles.length === 0) {
                                    var vacio = document.createElement('li');
                                    vacio.className = 'user-search__empty';
                                    vacio.textContent = 'Ningún colaborador coincide con esa búsqueda.';
                                    list.appendChild(vacio);
                                    abrir();
                                    return;
                                }
                                visibles.forEach(function (persona, indice) {
                                    var item = document.createElement('li');
                                    var boton = document.createElement('button');
                                    boton.type = 'button';
                                    boton.className = 'user-search__option';
                                    boton.setAttribute('role', 'option');
                                    boton.textContent = etiqueta(persona);
                                    boton.addEventListener('mousedown', function (evento) {
                                        evento.preventDefault();
                                        elegir(persona);
                                    });
                                    item.appendChild(boton);
                                    list.appendChild(item);
                                    persona._indice = indice;
                                });
                                abrir();
                            }

                            function marcarActivo(delta) {
                                var opciones = list.querySelectorAll('.user-search__option');
                                if (!opciones.length) return;
                                activo = (activo + delta + opciones.length) % opciones.length;
                                opciones.forEach(function (opcion, indice) {
                                    opcion.classList.toggle('is-active', indice === activo);
                                });
                                opciones[activo].scrollIntoView({ block: 'nearest' });
                            }

                            input.addEventListener('focus', render);
                            input.addEventListener('input', function () {
                                seleccionado = null;
                                hidden.value = '';
                                input.setCustomValidity('');
                                pintarCorreo(null);
                                render();
                            });
                            input.addEventListener('keydown', function (evento) {
                                if (evento.key === 'ArrowDown') {
                                    evento.preventDefault();
                                    if (list.hidden) render();
                                    marcarActivo(1);
                                } else if (evento.key === 'ArrowUp') {
                                    evento.preventDefault();
                                    marcarActivo(-1);
                                } else if (evento.key === 'Enter' && !list.hidden) {
                                    var opciones = list.querySelectorAll('.user-search__option');
                                    if (opciones.length) {
                                        evento.preventDefault();
                                        var indice = activo >= 0 ? activo : 0;
                                        opciones[indice].dispatchEvent(new MouseEvent('mousedown'));
                                    }
                                } else if (evento.key === 'Escape') {
                                    cerrar();
                                }
                            });
                            document.addEventListener('click', function (evento) {
                                if (!root.contains(evento.target)) cerrar();
                            });
                            if (form) {
                                form.addEventListener('submit', function (evento) {
                                    if (!hidden.value) {
                                        evento.preventDefault();
                                        input.setCustomValidity('Seleccione un colaborador de la lista.');
                                        input.reportValidity();
                                    }
                                });
                            }

                            if (hidden.value) {
                                var previa = personas.find(function (persona) { return persona.id === hidden.value; });
                                if (previa) elegir(previa);
                            }
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
                                        <span class="text-truncate">{{ ($item->emitido_delegado ? $item->decano : $item->emitidoPor)?->nombreCompleto() }}</span>
                                        @if($item->emitido_delegado)
                                            <span class="field-hint">A nombre del Decano</span>
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
