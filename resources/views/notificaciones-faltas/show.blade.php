@extends('layouts.app')

@section('title', $notificacion->codigo_oficio)

@section('content')
<section class="stack notif-th-page">
    <div class="tramite-header-bar">
        <a href="{{ route('notificaciones-faltas.index') }}" class="tramite-header-bar__back" aria-label="Volver al listado">←</a>
        <div>
            <h1 class="tramite-header-bar__title">Notificación Talento Humano</h1>
            <p class="notif-th-header-bar__code">{{ $notificacion->codigo_oficio }}</p>
        </div>
    </div>

    <div class="tramite-summary">
        <span><strong>Colaborador:</strong> {{ $notificacion->destinatario?->nombreCompleto() }}</span>
        <span><strong>Fecha falta:</strong> {{ $notificacion->fecha_falta->format('d/m/Y') }}</span>
        <span>
            <strong>Estado:</strong>
            @if($notificacion->tiene_justificacion_aprobada)
                Justificación aprobada en SAVA
            @else
                Pendiente de justificación
            @endif
        </span>
    </div>

    <div class="row notif-th-show-actions">
        <a href="{{ route('notificaciones-faltas.preview', $notificacion) }}" class="btn btn--primary" target="_blank" rel="noopener">Vista previa del oficio</a>
        <a href="{{ route('notificaciones-faltas.descargar', $notificacion) }}" class="btn btn--secondary">Descargar Word (.docx)</a>
        <form method="POST" action="{{ route('notificaciones-faltas.reenviar-correo', $notificacion) }}">
            @csrf
            <button type="submit" class="btn btn--secondary">Reenviar correo al colaborador</button>
        </form>
    </div>

    @if($notificacion->aviso_correo_enviado_at)
        <div class="alert alert--success" role="status">
            Correo enviado al colaborador notificado
            <strong>{{ $notificacion->destinatario?->nombreCompleto() }}</strong>
            en <strong>{{ $notificacion->aviso_correo_destino ?? $notificacion->destinatario?->email }}</strong>
            el {{ $notificacion->aviso_correo_enviado_at->format('d/m/Y H:i') }}.
        </div>
    @elseif($notificacion->aviso_correo_error)
        <div class="alert alert--error" role="alert">
            <strong>Correo no entregado:</strong> {{ $notificacion->aviso_correo_error }}
        </div>
    @endif

    <article class="card notif-th-detail-card">
        <header class="notif-th-detail-card__head">
            <h2 class="notif-th-detail-card__title">Datos del documento</h2>
            <p class="notif-th-detail-card__hint">Información registrada para el oficio y el aviso al colaborador</p>
        </header>
        <dl class="detail-list detail-list--rich">
            <div>
                <dt>Colaborador</dt>
                <dd>{{ $notificacion->destinatario?->nombreCompleto() }}</dd>
                <p class="detail-list__meta">{{ $notificacion->destinatario?->cedula ?: 'Sin cédula' }} · {{ $notificacion->destinatario?->email }}</p>
            </div>
            <div>
                <dt>Tipo de marcación</dt>
                <dd>{{ \App\Support\NotificacionFaltaMarcacion::label($notificacion->tipo_marcacion) }}</dd>
            </div>
            <div>
                <dt>Firma institucional</dt>
                <dd>{{ $notificacion->decano?->nombreCompleto() }}</dd>
                <p class="detail-list__meta">Decano de FACIVITEC</p>
            </div>
            <div>
                <dt>Registro en SAVA</dt>
                <dd>{{ $notificacion->created_at->format('d/m/Y H:i') }}</dd>
                <p class="detail-list__meta">
                    Emitido por {{ $notificacion->emitidoPor?->nombreCompleto() }}
                    @if($notificacion->emitido_delegado)
                        (secretaría delegada · Decano s)
                    @endif
                </p>
            </div>
            <div class="detail-list__span2">
                <dt>Justificación en trámites</dt>
                <dd class="detail-list__status">
                    @if($notificacion->tiene_justificacion_aprobada)
                        <span class="badge badge--success">Cubierta</span>
                        <span class="detail-list__inline">Existe solicitud aprobada que incluye esta fecha.</span>
                    @else
                        <span class="badge badge--warning">Sin cubrir</span>
                        <span class="detail-list__inline">No hay solicitud aprobada para esta fecha.</span>
                    @endif
                </dd>
            </div>
            @if($notificacion->notas)
                <div class="detail-list__span2">
                    <dt>Notas internas</dt>
                    <dd class="detail-list__notes">{{ $notificacion->notas }}</dd>
                </div>
            @endif
        </dl>
    </article>
</section>
@endsection
