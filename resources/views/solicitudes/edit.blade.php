@extends('layouts.app')

@section('title', 'Editar solicitud')

@section('content')
<section class="stack">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Editar solicitud</h1>
            <p class="field-hint" style="margin: 0.35rem 0 0;">
                Tipo: <strong>{{ $solicitud->tipo->label() }}</strong> (no se puede cambiar; crea un trámite nuevo si te equivocaste).
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
            <input type="hidden" name="tipo" value="{{ $solicitud->tipo->value }}">

            <div class="form-grid">
                <label class="field">
                    <span class="field__label">Fecha inicio *</span>
                    <input type="date" name="fecha_inicio" required class="field__input" value="{{ old('fecha_inicio', $solicitud->fecha_inicio->format('Y-m-d')) }}">
                </label>
                <label class="field">
                    <span class="field__label">Fecha fin *</span>
                    <input type="date" name="fecha_fin" required class="field__input" value="{{ old('fecha_fin', $solicitud->fecha_fin->format('Y-m-d')) }}">
                </label>
                <label class="field field--full">
                    <span class="field__label">Motivo *</span>
                    <textarea name="motivo" required rows="4" class="field__input">{{ old('motivo', $solicitud->motivo) }}</textarea>
                </label>
            </div>

            @include('solicitudes._adjuntos-field', [
                'label' => 'Otros documentos de respaldo',
                'solicitud' => $solicitud,
                'anexos' => $anexos,
                'requiredWhenEnfermedad' => $solicitud->tipo === \App\Enums\SolicitudTipo::Enfermedad,
            ])

            <div class="row" style="gap: 0.65rem; flex-wrap: wrap;">
                <button type="submit" name="borrador" value="1" class="btn btn--primary">Guardar cambios</button>
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
