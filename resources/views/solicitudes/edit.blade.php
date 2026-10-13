@extends('layouts.app')

@section('title', 'Editar solicitud')

@section('content')
<section class="stack">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Editar solicitud</h1>
        </div>
    </header>

    <article class="card stack">
        <form method="POST" action="{{ route('solicitudes.update', $solicitud) }}" enctype="multipart/form-data" class="stack">
            @csrf
            @method('PUT')
            <div class="form-grid">
                <label class="field">
                    <span class="field__label">Tipo *</span>
                    <select name="tipo" required class="field__input">
                        @foreach($tipos as $tipo)
                            <option value="{{ $tipo->value }}" @selected(old('tipo', $solicitud->tipo->value) === $tipo->value)>{{ $tipo->label() }}</option>
                        @endforeach
                    </select>
                </label>
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
                <label class="field field--full">
                    <span class="field__label">Justificativo</span>
                    <input type="file" name="justificativo" class="field__input">
                    @if($solicitud->justificativo_nombre)
                        <span class="field-hint">Actual: {{ $solicitud->justificativo_nombre }}</span>
                    @endif
                </label>
            </div>
            <div class="row">
                <button type="submit" name="borrador" value="0" class="btn btn--primary">Guardar cambios</button>
                @if($solicitud->estado === \App\Enums\SolicitudEstado::EnBorrador)
                    <button type="submit" name="borrador" value="1" class="btn btn--secondary">Mantener borrador</button>
                @endif
            </div>
        </form>

        @can('delete', $solicitud)
            <form method="POST" action="{{ route('solicitudes.destroy', $solicitud) }}" onsubmit="return confirm('¿Eliminar borrador?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn--danger btn--sm">Eliminar borrador</button>
            </form>
        @endcan
    </article>
</section>
@endsection
