<p class="field-hint" style="margin: 0;">
    <a href="{{ route('solicitudes.firmar', $solicitud) }}">← Elegir otro método</a>
</p>

<article class="card stack" style="gap: 1rem;">
    <div>
        <h2 class="solicitud-section-title" style="margin: 0;">Subir oficio firmado manualmente</h2>
        <p class="field-hint" style="margin: 0.35rem 0 0;">
            1) Descarga el oficio. 2) Fírmalo (impreso, escaneado o en Word/PDF). 3) Sube el archivo firmado.
        </p>
    </div>

    <div class="row" style="gap: 0.5rem; flex-wrap: wrap;">
        <a href="{{ $oficioDescargarUrl }}" class="btn btn--secondary" download>Descargar PDF</a>
        @if($oficioUsaDocx ?? false)
            <a href="{{ $oficioDocxUrl }}" class="btn btn--secondary" download>Descargar Word (.docx)</a>
        @endif
        <a href="{{ $oficioPreviewUrl }}" class="btn btn--ghost btn--sm" target="_blank" rel="noopener">Ver vista previa</a>
    </div>

    <form method="POST" action="{{ route('solicitudes.subir-oficio-firmado', $solicitud) }}" enctype="multipart/form-data" class="stack" style="gap: 0.75rem;" data-loading-label="Guardando oficio firmado…">
        @csrf
        <label class="field">
            <span class="field__label">Oficio firmado (PDF recomendado) *</span>
            <input type="file" name="oficio_firmado_docente" class="field__input" accept=".pdf,.png,.jpg,.jpeg" required>
            <span class="field-hint">Usa el mismo documento que descargaste, ya con tu firma.</span>
        </label>
        <button type="submit" class="btn btn--primary">Guardar oficio firmado</button>
    </form>
</article>
