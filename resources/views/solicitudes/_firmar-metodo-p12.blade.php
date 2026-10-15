<p class="field-hint" style="margin: 0;">
    <a href="{{ route('solicitudes.firmar', $solicitud) }}">← Elegir otro método</a>
</p>

<article class="card stack" style="gap: 1rem;">
    <div>
        <h2 class="solicitud-section-title" style="margin: 0;">Firma con certificado .p12</h2>
        <p class="field-hint" style="margin: 0.35rem 0 0;">
            Sube tu archivo de firma electrónica (.p12 o .pfx), ingresa la contraseña y aplica la firma al oficio.
            El certificado <strong>no se guarda</strong> en el servidor.
        </p>
    </div>

    <div class="field">
        <span class="field__label">Vista previa del oficio</span>
        <iframe
            src="{{ $pdfFirmaUrl }}"
            title="Vista previa del oficio"
            style="width: 100%; min-height: 420px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: #525659;"
        ></iframe>
        <a href="{{ $oficioPreviewUrl }}" class="btn btn--ghost btn--sm" target="_blank" rel="noopener" style="margin-top: 0.35rem;">Abrir vista previa en pestaña nueva</a>
    </div>

    <form
        method="POST"
        action="{{ route('solicitudes.firmar-oficio-p12', $solicitud) }}"
        enctype="multipart/form-data"
        class="stack"
        style="gap: 0.75rem;"
        data-loading-label="Firmando oficio con certificado…"
    >
        @csrf
        <label class="field">
            <span class="field__label">Archivo de firma (.p12 o .pfx) *</span>
            <input type="file" name="p12" accept=".p12,.pfx" required class="field__input">
        </label>
        <label class="field">
            <span class="field__label">Contraseña del certificado *</span>
            <input type="password" name="p12_password" required class="field__input" placeholder="Contraseña del .p12" autocomplete="off">
        </label>
        <button type="submit" class="btn btn--primary">Aplicar firma al oficio</button>
    </form>
</article>
