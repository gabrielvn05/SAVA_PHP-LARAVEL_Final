@extends('layouts.app')

@section('title', 'Nueva solicitud')

@section('content')
<section class="stack">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Nueva solicitud</h1>
        </div>
        <a href="{{ route('solicitudes.wizard') }}" class="btn btn--secondary btn--sm">Usar asistente</a>
    </header>

    <article class="card stack">
        <form method="POST" action="{{ route('solicitudes.store') }}" enctype="multipart/form-data" class="stack">
            @csrf
            <div class="form-grid">
                <label class="field">
                    <span class="field__label">Tipo *</span>
                    <select name="tipo" required class="field__input">
                        @foreach($tipos as $tipo)
                            <option value="{{ $tipo->value }}" @selected(old('tipo') === $tipo->value)>{{ $tipo->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span class="field__label">Fecha inicio *</span>
                    <input type="date" name="fecha_inicio" required class="field__input" value="{{ old('fecha_inicio') }}">
                </label>
                <label class="field">
                    <span class="field__label">Fecha fin *</span>
                    <input type="date" name="fecha_fin" required class="field__input" value="{{ old('fecha_fin') }}">
                </label>
                <label class="field field--full">
                    <span class="field__label">Motivo *</span>
                    <textarea name="motivo" required rows="4" class="field__input">{{ old('motivo') }}</textarea>
                </label>
                <label class="field field--full">
                    <span class="field__label">Justificativo (opcional)</span>
                    <input type="file" name="justificativo" class="field__input">
                </label>
            </div>
            <div class="row">
                <button type="submit" name="borrador" value="0" class="btn btn--primary">Enviar solicitud</button>
                <button type="submit" name="borrador" value="1" class="btn btn--secondary">Guardar borrador</button>
            </div>
        </form>
    </article>
</section>
@endsection
