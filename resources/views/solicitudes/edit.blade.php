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

            @if($anexos !== [])
                <fieldset class="stack" style="border: 0; padding: 0; margin: 0;">
                    <legend class="field__label">Archivos actuales</legend>
                    <p class="field-hint" style="margin: 0 0 0.5rem;">Marca los que deseas quitar antes de subir reemplazos.</p>
                    <ul class="stack" style="list-style: none; padding: 0; margin: 0; gap: 0.5rem;">
                        @foreach($anexos as $anexo)
                            <li>
                                <label class="row" style="align-items: center; gap: 0.5rem;">
                                    <input type="checkbox" name="eliminar_anexos[]" value="{{ $anexo['path'] }}">
                                    <span>{{ $anexo['nombre'] ?? 'Documento' }}</span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </fieldset>
            @endif

            <div class="form-grid">
                <label class="field field--full">
                    <span class="field__label">Reemplazar o agregar documento principal</span>
                    <input type="file" name="justificativo" class="field__input" accept=".pdf,.png,.jpg,.jpeg">
                </label>
                <label class="field field--full">
                    <span class="field__label">Anexos adicionales</span>
                    <input type="file" name="anexos[]" class="field__input" multiple accept=".pdf,.png,.jpg,.jpeg">
                    <span class="field-hint">Hasta 10 archivos en total y 10 MB de peso combinado.</span>
                </label>
            </div>

            <div class="row" style="gap: 0.65rem; flex-wrap: wrap;">
                <button type="submit" name="borrador" value="0" class="btn btn--primary">Guardar cambios</button>
                @if($solicitud->estado === \App\Enums\SolicitudEstado::EnBorrador)
                    <button type="submit" name="borrador" value="1" class="btn btn--secondary">Mantener borrador</button>
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
