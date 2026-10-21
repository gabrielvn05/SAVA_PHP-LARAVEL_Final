<p class="field-hint" style="margin: 0;">
    <a href="{{ route('solicitudes.firmar', $solicitud) }}">← Elegir otro método</a>
</p>

<article
    class="card stack"
    data-oficio-firma-root
    data-firma-modo="p12-posicion"
    data-pdf-url="{{ $pdfFirmaUrl }}"
    style="gap: 1rem;"
>
    <div>
        <h2 class="solicitud-section-title" style="margin: 0;">Firma con certificado .p12</h2>
        <p class="field-hint" style="margin: 0.35rem 0 0;">
            1) Sube el archivo .p12. 2) Ingresa la contraseña. 3) Haz clic en el documento donde debe ir la firma. 4) Aplica la firma.
            El PDF queda con la firma digital que FirmaEC reconoce. Ese PDF <strong>sí se guarda</strong> en el trámite; el archivo <strong>.p12 no se almacena</strong>.
            Si ya descargaste un oficio anterior, fírmalo de nuevo: ese archivo no cambia.
        </p>
    </div>

    <form
        method="POST"
        action="{{ route('solicitudes.firmar-oficio-p12', $solicitud) }}"
        data-firma-form
        enctype="multipart/form-data"
        class="stack"
        style="gap: 1rem;"
        data-loading-label="Firmando oficio con certificado…"
    >
        @csrf
        <input type="hidden" name="_return_modo" value="p12">
        <input type="hidden" name="firma_page" value="{{ old('firma_page', 1) }}" data-firma-page-input>
        <input type="hidden" name="firma_x" value="{{ old('firma_x', 75) }}" data-firma-x-input>
        <input type="hidden" name="firma_y" value="{{ old('firma_y', 88) }}" data-firma-y-input>

        <label class="field">
            <span class="field__label">Archivo de firma (.p12 o .pfx) *</span>
            <input type="file" name="p12" accept=".p12,.pfx" required class="field__input" data-firma-p12-file>
        </label>

        <div class="stack" style="gap: 0.75rem;" data-firma-p12-password-wrap hidden>
            <label class="field">
                <span class="field__label">Contraseña del certificado *</span>
                <input
                    type="password"
                    name="p12_password"
                    class="field__input"
                    placeholder="Contraseña del .p12"
                    autocomplete="off"
                    required
                    value="{{ old('p12_password') }}"
                    data-firma-p12-password
                >
            </label>
        </div>

        <div class="stack" style="gap: 0.75rem;" data-firma-p12-preview-wrap hidden>
            <div class="firma-pdf-positioner">
                <p class="field-hint" style="margin: 0 0 0.5rem; color: #fff; padding: 0 1rem;">
                    Haz clic en el documento para ubicar la firma (última página)
                </p>
                <button type="button" class="firma-pdf-positioner__page" data-firma-page>
                    <canvas class="firma-pdf-positioner__canvas" data-firma-page-canvas></canvas>
                    <span class="firma-pdf-positioner__mark" data-firma-mark hidden>Firma aquí</span>
                </button>
            </div>
            <a href="{{ $oficioPreviewUrl }}" class="btn btn--ghost btn--sm" target="_blank" rel="noopener">Abrir vista previa en pestaña nueva</a>
        </div>

        <p class="field-hint" data-firma-status style="margin: 0; color: var(--color-danger);"></p>

        <button type="submit" class="btn btn--primary" data-firma-submit>Aplicar firma al oficio</button>
    </form>
</article>
