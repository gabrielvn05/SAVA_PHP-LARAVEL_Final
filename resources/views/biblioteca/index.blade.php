@extends('layouts.app')

@section('title', 'Biblioteca')

@section('content')
<section class="stack">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Biblioteca</h1>
            <p class="field-hint" style="margin: 0.35rem 0 0;">
                Oficios institucionales para consulta.
                @if($puedeEditar)
                    Como Secretaría del Decano puedes subir y quitar los archivos que hagan falta.
                @else
                    Esta cuenta solo puede leer y descargar los documentos.
                @endif
            </p>
        </div>
    </header>

    @if($puedeEditar)
        <article class="card stack">
            <h2 style="margin: 0; font-size: 1.05rem;">Subir archivo</h2>
            <form method="POST" action="{{ route('biblioteca.store') }}" enctype="multipart/form-data" class="form-grid">
                @csrf
                <label class="field">
                    <span class="field__label">Título</span>
                    <input type="text" name="titulo" class="field__input" value="{{ old('titulo') }}" maxlength="180" placeholder="Si lo dejas vacío, se usa el nombre del archivo">
                    @error('titulo')
                        <p class="field-hint" style="color: var(--color-danger);">{{ $message }}</p>
                    @enderror
                </label>
                <label class="field">
                    <span class="field__label">Archivo *</span>
                    <input type="file" name="archivo" class="field__input" required accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                    <p class="field-hint" style="margin: 0.35rem 0 0;">PDF o Word, hasta 20 MB.</p>
                    @error('archivo')
                        <p class="field-hint" style="color: var(--color-danger);">{{ $message }}</p>
                    @enderror
                </label>
                <div class="field field--full">
                    <button type="submit" class="btn btn--primary">Subir a la biblioteca</button>
                </div>
            </form>
        </article>
    @endif

    <article class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Fecha</th>
                        <th>Autor</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documentos as $documento)
                        <tr>
                            <td data-label="Documento">{{ $documento->titulo }}</td>
                            <td data-label="Fecha">{{ $documento->fechaLegible() }}</td>
                            <td data-label="Autor">{{ $documento->autor_nombre }}</td>
                            <td data-label="Acciones">
                                <div class="row" style="gap: 0.4rem; flex-wrap: wrap;">
                                    @if($documento->esPdf())
                                        <a href="{{ route('biblioteca.ver', $documento) }}" class="btn btn--secondary btn--sm" target="_blank" rel="noopener">Ver</a>
                                    @endif
                                    <a href="{{ route('biblioteca.descargar', $documento) }}" class="btn btn--secondary btn--sm">Descargar</a>
                                    @if($puedeEditar)
                                        <form method="POST" action="{{ route('biblioteca.destroy', $documento) }}" class="inline-form" style="margin: 0;" onsubmit="return confirm('¿Quitar este documento de la biblioteca?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn--danger btn--sm">Quitar</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">Todavía no hay documentos en la biblioteca.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>
</section>
@endsection
