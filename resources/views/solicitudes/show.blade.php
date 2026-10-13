@extends('layouts.app')

@section('title', 'Solicitud')

@php
    $d = $solicitud->detalle ?? [];
    $fmtFecha = function (?string $iso): string {
        if (! $iso) {
            return 'Pendiente';
        }
        try {
            return \Carbon\Carbon::parse($iso)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return $iso;
        }
    };
    $estado = $solicitud->estado;
@endphp

@section('content')
<section class="stack">
    <div class="tramite-header-bar">
        <a
            href="{{ $esStaff ? route('solicitudes.proceso') : route('solicitudes.index') }}"
            class="tramite-header-bar__back"
            aria-label="Volver"
        >←</a>
        <h1 class="tramite-header-bar__title">Detalle del trámite</h1>
    </div>

    <div class="tramite-summary">
        <span>
            <strong>Trámite Nº:</strong> {{ $d['codigo_tramite'] ?? $solicitud->id }}
        </span>
        <span>
            <strong>Fecha de ingreso:</strong> {{ $solicitud->created_at->format('Y-m-d H:i') }}
        </span>
    </div>

    <article class="card stack">
        <div class="row" style="justify-content: space-between; align-items: center;">
            <div>
                <p class="field-hint" style="margin: 0;">Solicitante</p>
                <strong>{{ $solicitud->creador->nombreCompleto() }}</strong>
            </div>
            <span class="badge {{ $estado->badgeClass() }}">{{ $estado->label() }}</span>
        </div>

        <div class="tramite-timeline" role="list" aria-label="Fases del trámite">
            @foreach($timeline as $index => $event)
                @php
                    $isLast = $index === count($timeline) - 1;
                    $lineClass = $event['status'] === 'rejected'
                        ? 'tramite-timeline__line tramite-timeline__line--rejected'
                        : (in_array($event['status'], ['completed', 'current'], true)
                            ? 'tramite-timeline__line tramite-timeline__line--done'
                            : 'tramite-timeline__line');
                    $itemClass = 'tramite-timeline__item tramite-timeline__item--'.$event['status'];
                    if ($isLast) {
                        $itemClass .= ' tramite-timeline__item--last';
                    }
                    if ($event['id'] === 'final' && $event['status'] === 'completed') {
                        $itemClass .= ' tramite-timeline__item--approved';
                    }
                @endphp
                <div role="listitem" class="{{ $itemClass }}">
                    <div class="tramite-timeline__track">
                        @unless($isLast)
                            <div class="{{ $lineClass }}" aria-hidden="true"></div>
                        @endunless
                        <span class="tramite-timeline__dot tramite-timeline__dot--{{ $event['status'] }}" aria-hidden="true"></span>
                    </div>
                    <div class="tramite-timeline__icon" aria-hidden="true">{{ $event['icon'] }}</div>
                    <div class="tramite-timeline__body">
                        <div class="tramite-timeline__label-row">
                            <p class="tramite-timeline__label">{{ $event['label'] }}</p>
                            @if($event['status'] === 'current')
                                <span class="tramite-timeline__badge">Fase actual</span>
                            @endif
                            @if($event['status'] === 'pending')
                                <span class="tramite-timeline__badge tramite-timeline__badge--pending">Pendiente</span>
                            @endif
                            @if($event['id'] === 'final' && $event['status'] === 'completed')
                                <span class="tramite-timeline__badge tramite-timeline__badge--success">Aprobado</span>
                            @endif
                            @if($event['id'] === 'final' && $event['status'] === 'rejected')
                                <span class="tramite-timeline__badge tramite-timeline__badge--rejected">Rechazado</span>
                            @endif
                        </div>
                        <p class="tramite-timeline__fecha">{{ $fmtFecha($event['fecha'] ?? null) }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </article>

    <article class="card stack solicitud-preview">
        <h2 class="solicitud-preview__title">Vista previa de la solicitud</h2>
        <x-doc-viewer
            title="Oficio institucional"
            :file-name="($oficioVistaPdf ?? false) ? (($d['codigo_tramite'] ?? 'oficio').'.pdf') : (($d['codigo_tramite'] ?? 'oficio').'.docx')"
            :src="$oficioPreviewUrl ?? route('solicitudes.preview-oficio', $solicitud)"
            :download-href="$oficioDescargarUrl ?? route('solicitudes.preview-oficio', $solicitud)"
            :kind="($oficioVistaPdf ?? false) ? 'pdf' : (($oficioUsaPlantillaDocx ?? false) ? 'docx' : 'html')"
        />

        @foreach($documentos as $documento)
            <x-doc-viewer
                :title="$documento['nombre']"
                :file-name="$documento['nombre']"
                :src="$documento['url']"
                :kind="$documento['kind']"
            />
        @endforeach
    </article>

    @if($solicitud->observaciones_secretaria || $solicitud->observaciones_decano)
        <article class="card stack">
            <h2 class="solicitud-section-title">Observaciones</h2>
            @if($solicitud->observaciones_secretaria)
                <div>
                    <p class="field-hint" style="margin: 0;">
                        @if($solicitud->revisor)
                            {{ $solicitud->revisor->nombreCompleto() }}
                            ({{ $solicitud->revisor->rol->label() }})
                        @else
                            Secretaría
                        @endif
                    </p>
                    <p style="margin: 0.25rem 0 0;">{{ $solicitud->observaciones_secretaria }}</p>
                </div>
            @endif
            @if($solicitud->observaciones_decano)
                <div>
                    <p class="field-hint" style="margin: 0;">
                        @if($solicitud->firmante)
                            {{ $solicitud->firmante->nombreCompleto() }}
                            ({{ $solicitud->firmante->rol->label() }})
                        @else
                            Decano
                        @endif
                    </p>
                    <p style="margin: 0.25rem 0 0;">{{ $solicitud->observaciones_decano }}</p>
                </div>
            @endif
        </article>
    @endif

    @if($puedeActuarSecretaria || $puedeActuarDecano)
        @php
            $accionUrl = $puedeActuarSecretaria
                ? route('solicitudes.revisar', $solicitud)
                : route('solicitudes.aprobar', $solicitud);
            $campoObservacion = $puedeActuarSecretaria ? 'observaciones_secretaria' : 'observaciones_decano';
        @endphp
        <article class="card stack" data-proceso-acciones>
            <h2 class="solicitud-section-title">Acciones</h2>
            @if($puedeActuarDecano)
                <p class="field-hint" style="margin: 0;">Aprueba o rechaza con firma de Decano.</p>
            @endif
            <div class="row" style="gap: 0.65rem; flex-wrap: wrap;">
                <form method="POST" action="{{ $accionUrl }}" data-loading-label="Aprobando…">
                    @csrf
                    <input type="hidden" name="aprobado" value="1">
                    <button type="submit" class="btn btn--success">Aprobar</button>
                </form>
                <button type="button" class="btn btn--danger" data-rechazo-open>Rechazar</button>
            </div>
        </article>

        <div class="logout-modal" hidden data-rechazo-modal role="dialog" aria-modal="true" aria-labelledby="rechazo-modal-title">
            <button type="button" class="logout-modal__backdrop" aria-label="Cerrar" data-rechazo-close></button>
            <div class="logout-modal__panel">
                <form method="POST" action="{{ $accionUrl }}" data-loading-label="Rechazando…">
                    @csrf
                    <input type="hidden" name="aprobado" value="0">
                    <h2 id="rechazo-modal-title" class="logout-modal__title">Motivo del rechazo</h2>
                    <p class="logout-modal__text">Indica el motivo del rechazo.</p>
                    <label class="visually-hidden" for="comentario-rechazo">Motivo del rechazo</label>
                    <textarea
                        id="comentario-rechazo"
                        name="{{ $campoObservacion }}"
                        rows="4"
                        required
                        maxlength="5000"
                        placeholder="Escriba el motivo del rechazo…"
                        style="width: 100%; margin-bottom: 1rem; resize: vertical;"
                    ></textarea>
                    <div class="logout-modal__actions">
                        <button type="button" class="btn btn--ghost" data-rechazo-close>Cancelar</button>
                        <button type="submit" class="btn btn--danger">Confirmar rechazo</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if(auth()->id() === $solicitud->creado_por)
        @can('update', $solicitud)
            <a href="{{ route('solicitudes.edit', $solicitud) }}" class="btn btn--primary">Editar solicitud</a>
        @endcan
    @endif
</section>
@endsection
