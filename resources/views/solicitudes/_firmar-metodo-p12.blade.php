<p class="field-hint" style="margin: 0;">
    <a href="{{ route('solicitudes.firmar', $solicitud) }}">← Elegir otro método</a>
</p>

<article
    class="card stack"
    data-oficio-firma-root
    data-firma-require-p12="1"
    data-pdf-url="{{ $pdfFirmaUrl }}"
    style="gap: 1rem;"
>
    <div>
        <h2 class="solicitud-section-title" style="margin: 0;">Firma con certificado .p12</h2>
        <p class="field-hint" style="margin: 0.35rem 0 0;">
            1) Archivo .p12 y contraseña. 2) Dibuja tu rúbrica. 3) Clic en el documento para ubicarla. 4) «Aplicar firma al oficio».
            El certificado <strong>no se guarda</strong> en el servidor.
        </p>
    </div>

    <form method="POST" action="{{ route('solicitudes.firmar-p12-documento', $solicitud) }}" data-firma-form enctype="multipart/form-data" data-loading-label="Firmando oficio con certificado…" class="stack" style="gap: 1rem;">
        @csrf
        <input type="file" name="firma_imagen" data-firma-file-input hidden accept="image/png">
        <input type="hidden" name="firma_page" value="1" data-firma-page-input>
        <input type="hidden" name="firma_x" value="75" data-firma-x-input>
        <input type="hidden" name="firma_y" value="88" data-firma-y-input>

        <label class="field">
            <span class="field__label">Archivo de firma (.p12 o .pfx) *</span>
            <input type="file" name="p12" accept=".p12,.pfx" required class="field__input" data-firma-p12-file>
        </label>
        <label class="field">
            <span class="field__label">Contraseña del certificado *</span>
            <input type="password" name="p12_password" required class="field__input" placeholder="Contraseña del .p12" autocomplete="off" data-firma-p12-password>
        </label>

        <label class="field">
            <span class="field__label">Rúbrica visible (dibuja con mouse o dedo) *</span>
            <canvas data-firma-pad width="420" height="130" style="width: 100%; max-width: 420px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); touch-action: none;"></canvas>
            <button type="button" class="btn btn--ghost btn--sm" data-firma-clear style="margin-top: 0.35rem;">Limpiar rúbrica</button>
        </label>

        <div class="firma-pdf-positioner">
            <p class="field-hint" style="margin: 0 0 0.5rem; color: #fff; padding: 0 1rem;">Haz clic en el documento para ubicar la firma (última página)</p>
            <button type="button" class="firma-pdf-positioner__page" data-firma-page>
                <canvas class="firma-pdf-positioner__canvas" data-firma-page-canvas></canvas>
                <span class="firma-pdf-positioner__mark" data-firma-mark hidden>Firma aquí</span>
            </button>
        </div>

        <p class="field-hint" data-firma-status style="margin: 0; color: var(--color-danger);"></p>

        <button type="submit" class="btn btn--primary">Aplicar firma al oficio</button>
    </form>
</article>
