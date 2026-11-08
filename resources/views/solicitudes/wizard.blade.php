@extends('layouts.app')

@section('title', 'Nueva solicitud')

@php
    $perfilCompleto = trim($user->cedula) !== '' && trim($user->carrera) !== '';
    $minFechaLegible = \Illuminate\Support\Carbon::parse($minFecha)->locale('es')->translatedFormat('j M Y');
    $iconoTipo = [
        'permiso' => 'clock',
        'justificacion' => 'alarm',
        'viaje' => 'pin',
        'enfermedad' => 'cross',
        'calamidad_domestica' => 'home',
        'falta_marcado' => 'scan',
    ];
@endphp

@section('content')
<section class="stack page-enter">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Nueva solicitud</h1>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('solicitudes.index') }}" class="btn btn--secondary btn--sm">Volver</a>
            <a href="{{ route('solicitudes.create') }}" class="btn btn--secondary btn--sm">Formulario simple</a>
        </div>
    </header>

    <ol class="wizard-steps" aria-label="Pasos de la solicitud">
        <li class="wizard-steps__item is-current" id="wizard-progress-step-0" aria-current="step">
            <span class="wizard-steps__index">1</span>
            <span class="wizard-steps__label">Tipo</span>
        </li>
        <li class="wizard-steps__item" id="wizard-progress-step-1">
            <span class="wizard-steps__index">2</span>
            <span class="wizard-steps__label">Datos</span>
        </li>
    </ol>

    <form method="POST" action="{{ route('solicitudes.store') }}" enctype="multipart/form-data" class="stack" id="wizard-form" data-loading-label="Alistando para firma…">
        @csrf
        <input type="hidden" name="tipo" id="tipo-input" value="{{ old('tipo') }}" required>

        <article class="card stack wizard-panel" id="panel-tipo">
            <h2 class="wizard-panel__title">Tipo de solicitud</h2>
            <p class="field-hint wizard-panel__lead">
                Selecciona la opción que mejor describa tu caso. Esto define los campos del formulario.
            </p>

            <div class="wizard-tipo-grid">
                @foreach($tipos as $tipo)
                    <button type="button" class="wizard-tipo-card wizard-tipo-card--{{ $tipo->value }}" data-tipo="{{ $tipo->value }}">
                        <span class="wizard-tipo-card__icon" aria-hidden="true">
                            @include('partials.nav-icon', ['name' => $iconoTipo[$tipo->value] ?? 'file-plus', 'class' => 'wizard-tipo-card__glyph'])
                        </span>
                        <span class="wizard-tipo-card__body">
                            <strong class="wizard-tipo-card__title">{{ $tipo->label() }}</strong>
                            <span class="field-hint wizard-tipo-card__desc">{{ $tipo->description() }}</span>
                        </span>
                    </button>
                @endforeach
            </div>
            @error('tipo') <p class="field-hint field-hint--danger">{{ $message }}</p> @enderror

            <div class="row row--end">
                <button class="btn btn--primary" type="button" data-wizard-continue disabled>Continuar</button>
            </div>
        </article>

        <article class="card stack wizard-panel" id="panel-datos" hidden>
            <h2 class="wizard-panel__title">Datos del trámite</h2>
            <p class="wizard-who">A nombre de <strong>{{ $user->nombreCompleto() }}</strong> · {{ $user->rol->label() }}</p>

            @if(! $perfilCompleto)
                <div class="alert alert--warning" role="alert">
                    Completa tu cédula y carrera en
                    <a class="alert__link" href="{{ route('perfil.edit') }}">Perfil y configuración</a>
                    antes de crear solicitudes.
                </div>
            @endif

            <div class="wizard-field wizard-field--inasistencia" hidden>
                <label>Fecha de inasistencia *</label>
                <input type="date" name="fecha_inasistencia" min="{{ $minFecha }}" value="{{ old('fecha_inasistencia') }}">
                <p class="field-hint">No puede ser anterior al {{ $minFechaLegible }}.</p>
            </div>

            <div class="form-grid form-grid--2 wizard-field wizard-field--fechas" hidden>
                <div>
                    <label>Fecha inicio *</label>
                    <input type="date" name="fecha_inicio" min="{{ $minFecha }}" value="{{ old('fecha_inicio') }}">
                </div>
                <div>
                    <label>Fecha fin *</label>
                    <input type="date" name="fecha_fin" min="{{ $minFecha }}" value="{{ old('fecha_fin') }}">
                </div>
                <p class="field-hint wizard-date-note">No puede ser anterior al {{ $minFechaLegible }}.</p>
            </div>

            <div class="wizard-field wizard-field--permiso" hidden>
                <hr class="wizard-rule">
                <h3 class="wizard-section__title">Datos del permiso</h3>
                <p class="field-hint wizard-section__lead">Corresponde a un solo día o jornada laboral.</p>
                <div>
                    <label>Fecha del permiso *</label>
                    <input type="date" name="fecha_permiso" min="{{ $minFecha }}" value="{{ old('fecha_permiso') }}">
                    <p class="field-hint">No puede ser anterior al {{ $minFechaLegible }}.</p>
                </div>
                <div class="form-grid form-grid--2 wizard-follow">
                    <div>
                        <label>Hora de inicio de falta *</label>
                        <input type="time" name="hora_inicio_permiso" value="{{ old('hora_inicio_permiso') }}">
                    </div>
                    <div>
                        <label>Hora de fin de falta *</label>
                        <input type="time" name="hora_fin_permiso" value="{{ old('hora_fin_permiso') }}">
                    </div>
                </div>
            </div>

            <div class="wizard-field wizard-field--justificacion-atraso" hidden>
                <hr class="wizard-rule">
                <h3 class="wizard-section__title">Justificación por atraso</h3>
                <p class="field-hint wizard-section__lead">Solo puede registrarse el atraso de la jornada de hoy.</p>
                <div>
                    <label>Fecha del atraso *</label>
                    <input
                        type="date"
                        name="fecha_atraso"
                        min="{{ $fechaHoy }}"
                        max="{{ $fechaHoy }}"
                        value="{{ old('fecha_atraso', $fechaHoy) }}"
                        readonly
                    >
                </div>
                <div class="form-grid form-grid--2 wizard-follow">
                    <div>
                        <label>Hora establecida de llegada *</label>
                        <input type="time" name="hora_llegada_establecida" value="{{ old('hora_llegada_establecida') }}">
                        <p class="field-hint">Horario oficial de inicio de jornada.</p>
                    </div>
                    <div>
                        <label>Hora real de llegada *</label>
                        <input type="time" name="hora_llegada_real" value="{{ old('hora_llegada_real') }}">
                        <p class="field-hint">Debe ser posterior a la hora establecida.</p>
                    </div>
                </div>
            </div>

            <div class="wizard-field wizard-field--justificacion-motivo" hidden>
                <div>
                    <label>Motivo del atraso *</label>
                    <select name="motivo_atraso" id="motivo-atraso-select">
                        <option value="">Seleccionar</option>
                        @foreach(\App\Enums\JustificacionAtrasoMotivo::cases() as $motivoAtraso)
                            <option
                                value="{{ $motivoAtraso->value }}"
                                data-hint="{{ $motivoAtraso->hint() }}"
                                @selected(old('motivo_atraso') === $motivoAtraso->value)
                            >{{ $motivoAtraso->label() }}</option>
                        @endforeach
                    </select>
                    <p class="field-hint field-hint--follow" id="motivo-atraso-hint"></p>
                </div>
            </div>

            <div class="wizard-field wizard-field--permiso-motivo" hidden>
                <div>
                    <label>Motivo *</label>
                    <select name="motivo_permiso" id="motivo-permiso-select">
                        <option value="">Seleccionar</option>
                        @foreach(\App\Enums\PermisoMotivo::cases() as $motivoPermiso)
                            <option
                                value="{{ $motivoPermiso->value }}"
                                data-hint="{{ $motivoPermiso->hint() }}"
                                @selected(old('motivo_permiso') === $motivoPermiso->value)
                            >{{ $motivoPermiso->label() }}</option>
                        @endforeach
                    </select>
                    <p class="field-hint field-hint--follow" id="motivo-permiso-hint"></p>
                    <div
                        id="permiso-cita-medica-aviso"
                        class="alert alert--warning alert--inset"
                        role="status"
                        @if(old('motivo_permiso') !== \App\Enums\PermisoMotivo::CitaMedica->value) hidden @endif
                    >
                        <strong>Comprobante de la cita.</strong>
                        {{ \App\Enums\PermisoMotivo::CitaMedica->avisoAdjunto() }}
                        Acepta PDF, PNG o JPG.
                    </div>
                </div>
                <div class="wizard-field wizard-field--permiso-motivo-otro wizard-follow" hidden>
                    <label>Especifique el motivo *</label>
                    <input type="text" name="motivo_permiso_otro" value="{{ old('motivo_permiso_otro') }}" maxlength="500">
                </div>
            </div>

            <div class="wizard-field wizard-field--enfermedad" hidden>
                <hr class="wizard-rule">
                <h3 class="wizard-section__title">Datos del certificado médico</h3>
                <div class="form-grid form-grid--2">
                    <div>
                        <label>Institución médica *</label>
                        <select name="institucion_medica_tipo" id="institucion-medica-tipo">
                            <option value="">Seleccionar</option>
                            @foreach(['IESS', 'Ministerio de Salud Pública', 'Hospital privado', 'Centro de salud', 'Otro'] as $inst)
                                <option value="{{ $inst }}" @selected(old('institucion_medica_tipo') === $inst)>{{ $inst }}</option>
                            @endforeach
                        </select>
                        <div
                            id="institucion-medica-aviso-iess"
                            class="alert alert--warning alert--inset"
                            role="status"
                            hidden
                        >
                            <strong>Certificado avalado por el IESS.</strong>
                            Si la institución no es IESS, debes adjuntar al final del formulario el certificado médico
                            <strong>avalado o aprobado por el IESS</strong> (PDF, PNG o JPG), además del certificado del establecimiento si lo tienes.
                        </div>
                    </div>
                    <div class="wizard-field wizard-field--institucion-nombre" hidden>
                        <label>Nombre del establecimiento *</label>
                        <input type="text" name="institucion_medica_nombre" value="{{ old('institucion_medica_nombre') }}" placeholder="Ej: Hospital Metropolitano">
                    </div>
                    <div>
                        <label>Médico tratante *</label>
                        <input type="text" name="medico_tratante" value="{{ old('medico_tratante') }}" placeholder="Dr. Juan Pérez">
                    </div>
                    <div>
                        <label>Fecha de emisión del certificado *</label>
                        <input type="date" name="fecha_emision_certificado" value="{{ old('fecha_emision_certificado') }}">
                    </div>
                    <div>
                        <label>Días de reposo</label>
                        <input type="text" name="dias_reposo" value="{{ old('dias_reposo') }}" inputmode="numeric" placeholder="Ej: 3">
                        <p class="field-hint">Si no aplica, déjalo vacío (se usará el mismo día de la inasistencia).</p>
                    </div>
                </div>
                <div>
                    <label>Diagnóstico *</label>
                    <textarea name="diagnostico" rows="4">{{ old('diagnostico') }}</textarea>
                </div>
            </div>

            <div class="wizard-field wizard-field--viaje" hidden>
                <hr class="wizard-rule">
                <h3 class="wizard-section__title">Datos de la visita de campo</h3>
                <div class="form-grid form-grid--2">
                    <div>
                        <label>Fecha inicio de la falta *</label>
                        <input type="date" name="fecha_inicio_viaje" min="{{ $minFecha }}" value="{{ old('fecha_inicio_viaje') }}">
                    </div>
                    <div>
                        <label>Fecha de retorno *</label>
                        <input type="date" name="fecha_fin_viaje" min="{{ $minFecha }}" value="{{ old('fecha_fin_viaje') }}">
                    </div>
                    <p class="field-hint wizard-date-note">No puede ser anterior al {{ $minFechaLegible }}.</p>
                </div>
                <div class="wizard-follow">
                    <p class="field-hint field-hint--emphasis">
                        Fechas del evento o actividad *
                    </p>
                    <div class="form-grid form-grid--2 wizard-event-grid">
                        <div>
                            <span class="field-hint">Inicio del evento</span>
                            <div class="form-grid form-grid--3 wizard-date-parts">
                                <div>
                                    <label class="field-hint">Día</label>
                                    <input type="number" name="evento_inicio_dia" min="1" max="31" inputmode="numeric"
                                        value="{{ old('evento_inicio_dia') }}" placeholder="15">
                                </div>
                                <div>
                                    <label class="field-hint">Mes</label>
                                    <select name="evento_inicio_mes">
                                        <option value="">Mes</option>
                                        @for($m = 1; $m <= 12; $m++)
                                            <option value="{{ $m }}" @selected((int) old('evento_inicio_mes') === $m)>
                                                {{ \App\Support\SolicitudViajeRules::mesLabel($m) }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                                <div>
                                    <label class="field-hint">Año</label>
                                    <input type="number" name="evento_inicio_anio" min="2000" max="2100" inputmode="numeric"
                                        value="{{ old('evento_inicio_anio', now()->year) }}" placeholder="{{ now()->year }}">
                                </div>
                            </div>
                        </div>
                        <div>
                            <span class="field-hint">Fin del evento</span>
                            <div class="form-grid form-grid--3 wizard-date-parts">
                                <div>
                                    <label class="field-hint">Día</label>
                                    <input type="number" name="evento_fin_dia" min="1" max="31" inputmode="numeric"
                                        value="{{ old('evento_fin_dia') }}" placeholder="20">
                                </div>
                                <div>
                                    <label class="field-hint">Mes</label>
                                    <select name="evento_fin_mes">
                                        <option value="">Mes</option>
                                        @for($m = 1; $m <= 12; $m++)
                                            <option value="{{ $m }}" @selected((int) old('evento_fin_mes') === $m)>
                                                {{ \App\Support\SolicitudViajeRules::mesLabel($m) }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                                <div>
                                    <label class="field-hint">Año</label>
                                    <input type="number" name="evento_fin_anio" min="2000" max="2100" inputmode="numeric"
                                        value="{{ old('evento_fin_anio', now()->year) }}" placeholder="{{ now()->year }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <label>Tipo de visita de campo *</label>
                    <select name="tipo_viaje_evento">
                        <option value="">Seleccionar</option>
                        <option value="estudio" @selected(old('tipo_viaje_evento') === 'estudio')>Estudio</option>
                        <option value="congreso_expositor" @selected(old('tipo_viaje_evento') === 'congreso_expositor')>Congreso – Expositor</option>
                        <option value="congreso_participante" @selected(old('tipo_viaje_evento') === 'congreso_participante')>Congreso – Participante (observador)</option>
                    </select>
                </div>
                <div>
                    <label>Nombre del lugar o institución de estudio *</label>
                    <input type="text" name="nombre_evento" value="{{ old('nombre_evento') }}">
                </div>
                <div>
                    <label>Lugar (ciudad, país) *</label>
                    <input type="text" name="lugar_evento" value="{{ old('lugar_evento') }}" placeholder="Ej: Quito, Ecuador">
                </div>
                <div>
                    <label>Rol específico (si aplica)</label>
                    <input type="text" name="rol_especifico" value="{{ old('rol_especifico') }}" placeholder="Ej: Ponente principal">
                    <p class="field-hint">Opcional.</p>
                </div>
            </div>

            <div class="wizard-field wizard-field--calamidad" hidden>
                <hr class="wizard-rule">
                <h3 class="wizard-section__title">Datos de calamidad doméstica</h3>
                <div>
                    <label>Tipo de calamidad *</label>
                    <select name="tipo_calamidad">
                        <option value="">Seleccionar</option>
                        <option value="fallecimiento_familiar" @selected(old('tipo_calamidad') === 'fallecimiento_familiar')>Fallecimiento de familiar</option>
                        <option value="emergencia_medica_familiar" @selected(old('tipo_calamidad') === 'emergencia_medica_familiar')>Emergencia médica grave de familiar</option>
                    </select>
                </div>
                <div>
                    <label>Nombre completo del familiar afectado *</label>
                    <input type="text" name="nombre_familiar" value="{{ old('nombre_familiar') }}">
                </div>
                <div>
                    <label>Parentesco (hasta segundo grado) *</label>
                    <select name="parentesco">
                        <option value="">Seleccionar</option>
                        <option value="conyuge" @selected(old('parentesco') === 'conyuge')>Cónyuge</option>
                        <option value="madre" @selected(old('parentesco') === 'madre')>Madre</option>
                        <option value="padre" @selected(old('parentesco') === 'padre')>Padre</option>
                        <option value="hermano" @selected(old('parentesco') === 'hermano')>Hermano(a)</option>
                        <option value="hijo" @selected(old('parentesco') === 'hijo')>Hijo(a)</option>
                    </select>
                </div>
                <div>
                    <label>Descripción del hecho *</label>
                    <textarea name="descripcion_hecho" rows="4">{{ old('descripcion_hecho') }}</textarea>
                </div>
                <div>
                    <label>Lugar donde ocurrió *</label>
                    <input type="text" name="lugar_suceso" value="{{ old('lugar_suceso') }}">
                </div>
                <div>
                    <label>Fecha del hecho *</label>
                    <input type="date" name="fecha_hecho" value="{{ old('fecha_hecho') }}">
                </div>
            </div>

            <div class="wizard-field wizard-field--falta" hidden>
                <hr class="wizard-rule">
                <h3 class="wizard-section__title">Reporte de novedad en marcación</h3>
                <p class="field-hint">Justificante por olvidos o fallas del sistema Face ID.</p>
                <div>
                    <label>Jornada *</label>
                    <select name="jornada">
                        <option value="">Seleccionar jornada</option>
                        <option value="primera_jornada" @selected(old('jornada') === 'primera_jornada')>Primera jornada</option>
                        <option value="segunda_jornada" @selected(old('jornada') === 'segunda_jornada')>Segunda jornada</option>
                        <option value="ambas" @selected(old('jornada') === 'ambas')>Ambas jornadas</option>
                    </select>
                </div>
                <div>
                    <label>Fecha del incidente *</label>
                    <input type="date" name="fecha_incidente" min="{{ $minFecha }}" value="{{ old('fecha_incidente') }}">
                </div>
                <div>
                    <label>Tipo de marcación omitida/fallida *</label>
                    <select name="tipo_marcacion_omitida" id="tipo-marcacion-omitida-select">
                        <option value="">Seleccionar</option>
                        <option value="entrada" @selected(old('tipo_marcacion_omitida') === 'entrada')>Marcación de entrada</option>
                        <option value="salida" @selected(old('tipo_marcacion_omitida') === 'salida')>Marcación de salida</option>
                        <option value="entrada_salida" @selected(old('tipo_marcacion_omitida') === 'entrada_salida')>Entrada y salida</option>
                    </select>
                </div>
                <div class="form-grid form-grid--2">
                    <div class="wizard-falta-hora--ingreso">
                        <label>Hora real de ingreso *</label>
                        <input type="time" name="hora_real_ingreso" value="{{ old('hora_real_ingreso') }}">
                    </div>
                    <div class="wizard-falta-hora--salida">
                        <label>Hora real de salida *</label>
                        <input type="time" name="hora_real_salida" value="{{ old('hora_real_salida') }}">
                    </div>
                </div>
            </div>

            <div class="wizard-field wizard-field--falta-motivo" hidden>
                <div>
                    <label>Motivo *</label>
                    <select name="motivo_falta_registro" id="motivo-falta-registro-select">
                        <option value="">Seleccionar</option>
                        @foreach(\App\Enums\FaltaMarcadoMotivo::cases() as $motivoFalta)
                            <option
                                value="{{ $motivoFalta->value }}"
                                data-hint="{{ $motivoFalta->hint() }}"
                                @selected(old('motivo_falta_registro') === $motivoFalta->value)
                            >{{ $motivoFalta->label() }}</option>
                        @endforeach
                    </select>
                    <p class="field-hint field-hint--follow" id="motivo-falta-registro-hint"></p>
                </div>
            </div>

            <div class="wizard-field wizard-field--motivo">
                <label>Motivo / observaciones</label>
                <textarea name="motivo" rows="3">{{ old('motivo') }}</textarea>
                <p class="field-hint">En trámites guiados se genera automáticamente si lo dejas vacío.</p>
            </div>

            <div class="wizard-field wizard-field--observaciones">
                <label>Observaciones adicionales</label>
                <textarea name="observaciones" rows="3">{{ old('observaciones') }}</textarea>
            </div>

            <hr class="wizard-rule">
            <h3 class="wizard-section__title">Documento de respaldo</h3>
            <p class="field-hint">
                Al enviar la solicitud se podrá consultar el oficio institucional desde el detalle del trámite.
            </p>
            <p class="field-hint wizard-section__lead">
                Al continuar elegirás <strong>cómo firmar el oficio</strong> (certificado .p12 o subida manual) y luego lo enviarás {{ ($user->rol ?? null) === \App\Enums\AppRole::Secretaria ? 'al Decano' : 'a Secretaría' }}.
            </p>
            @include('solicitudes._adjuntos-field', [
                'label' => 'Adjuntar documento de respaldo (opcional)',
                'existingCount' => 0,
                'requiredWhenEnfermedad' => false,
            ])

            <div class="row row--between">
                <button class="btn btn--secondary" type="button" data-wizard-back>Atrás</button>
                <button type="submit" class="btn btn--primary">Continuar a firma</button>
            </div>
        </article>
    </form>
</section>
@endsection
