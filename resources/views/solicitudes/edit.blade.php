@extends('layouts.app')

@section('title', 'Editar solicitud')

@section('content')
<section class="stack">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Editar solicitud</h1>
            <p class="field-hint" style="margin: 0.35rem 0 0;">
                Tipo: <strong>{{ $solicitud->tipo->label() }}</strong>. Aquí solo puedes subir o quitar archivos de respaldo.
            </p>
        </div>
        @if(($mostrarFirmaDocente ?? false) && $solicitud->estado === \App\Enums\SolicitudEstado::EnBorrador)
            <div class="page-header__actions">
                <a href="{{ route('solicitudes.firmar', $solicitud) }}" class="btn btn--primary btn--sm">Ir a firmar oficio</a>
            </div>
        @endif
    </header>

    <article class="card stack">
        <form method="POST" action="{{ route('solicitudes.update', $solicitud) }}" enctype="multipart/form-data" class="stack">
            @csrf
            @method('PUT')
            <dl class="stack" style="gap: 0.35rem; margin: 0;">
                <div>
                    <dt class="field__label">Fecha inicio</dt>
                    <dd style="margin: 0;">{{ $solicitud->fecha_inicio->format('d/m/Y') }}</dd>
                </div>
                <div>
                    <dt class="field__label">Fecha fin</dt>
                    <dd style="margin: 0;">{{ $solicitud->fecha_fin->format('d/m/Y') }}</dd>
                </div>
                <div>
                    <dt class="field__label">Motivo</dt>
                    <dd style="margin: 0; white-space: pre-wrap;">{{ $solicitud->motivo }}</dd>
                </div>
            </dl>

            @include('solicitudes._adjuntos-field', [
                'label' => ($solicitud->detalle['motivo_permiso'] ?? null) === \App\Enums\PermisoMotivo::CitaMedica->value
                    ? 'Orden o citación médica *'
                    : 'Otros documentos de respaldo',
                'solicitud' => $solicitud,
                'anexos' => $anexos,
                'requiredWhenEnfermedad' => $solicitud->tipo === \App\Enums\SolicitudTipo::Enfermedad
                    || ($solicitud->detalle['motivo_permiso'] ?? null) === \App\Enums\PermisoMotivo::CitaMedica->value,
                'requiredMessage' => ($solicitud->detalle['motivo_permiso'] ?? null) === \App\Enums\PermisoMotivo::CitaMedica->value
                    ? \App\Enums\PermisoMotivo::CitaMedica->avisoAdjunto()
                    : '',
            ])

            <div class="row" style="gap: 0.65rem; flex-wrap: wrap;">
                <button type="submit" name="borrador" value="1" class="btn btn--primary">Guardar archivos</button>
                @if(($mostrarFirmaDocente ?? false) && $solicitud->estado === \App\Enums\SolicitudEstado::EnBorrador)
                    <a href="{{ route('solicitudes.firmar', $solicitud) }}" class="btn btn--secondary">Continuar a firma</a>
                @endif
            </div>
        </form>

        @can('delete', $solicitud)
            <form method="POST" action="{{ route('solicitudes.destroy', $solicitud) }}" onsubmit="return confirm('¿Eliminar esta solicitud? Esta acción no se puede deshacer.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn--danger">Borrar solicitud</button>
            </form>
        @endcan
    </article>
</section>
@endsection
