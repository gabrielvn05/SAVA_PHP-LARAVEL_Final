@extends('layouts.app')

@section('title', 'Detalle de auditoría')

@section('content')
<section class="stack">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Detalle del evento</h1>
            <p class="page-header__subtitle">
                {{ \App\Support\AuditLogPresenter::accionLabel($log->action) }}
                en {{ \App\Support\AuditLogPresenter::tablaLabel($log->table_name) }}.
            </p>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('admin.auditoria.index') }}" class="btn btn--secondary btn--sm">Volver al listado</a>
        </div>
    </header>

    <article class="card">
        <dl class="detail-list">
            <div>
                <dt>Fecha</dt>
                <dd>{{ $log->changed_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</dd>
            </div>
            <div>
                <dt>Usuario</dt>
                <dd>
                    @if($log->changedBy)
                        {{ $log->changedBy->nombreCompleto() }}
                        <p class="detail-list__meta">{{ $log->changedBy->rol->label() }} · {{ $log->changedBy->email }}</p>
                    @else
                        Usuario eliminado
                    @endif
                </dd>
            </div>
            <div>
                <dt>Acción</dt>
                <dd>
                    <span class="badge {{ \App\Support\AuditLogPresenter::accionBadge($log->action) }}">
                        {{ \App\Support\AuditLogPresenter::accionLabel($log->action) }}
                    </span>
                </dd>
            </div>
            <div>
                <dt>Módulo</dt>
                <dd>{{ \App\Support\AuditLogPresenter::tablaLabel($log->table_name) }}</dd>
            </div>
            <div>
                <dt>Registro</dt>
                <dd class="text-code">{{ $log->record_id }}</dd>
            </div>
            <div>
                <dt>Dirección IP</dt>
                <dd>{{ $log->ip_address ?: '—' }}</dd>
            </div>
            <div class="detail-list__span2">
                <dt>Navegador</dt>
                <dd class="detail-list__notes">{{ $log->user_agent ?: '—' }}</dd>
            </div>
        </dl>
    </article>

    <div class="form-grid form-grid--2">
        <article class="card stack" style="gap: 0.65rem;">
            <h2 style="margin: 0; font-size: 1rem;">Datos anteriores</h2>
            <pre class="audit-json">{{ \App\Support\AuditLogPresenter::json($log->old_data) }}</pre>
        </article>
        <article class="card stack" style="gap: 0.65rem;">
            <h2 style="margin: 0; font-size: 1rem;">Datos nuevos</h2>
            <pre class="audit-json">{{ \App\Support\AuditLogPresenter::json($log->new_data) }}</pre>
        </article>
    </div>
</section>
@endsection
