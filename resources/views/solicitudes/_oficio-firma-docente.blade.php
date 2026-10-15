@php
    use App\Services\SolicitudOficioFirmadoService;
    use App\Support\SolicitudAdjuntos;

    $oficioFirmado = SolicitudOficioFirmadoService::oficioFirmadoEnAnexos($solicitud);
    $firmaMeta = ($solicitud->detalle ?? [])['firma_docente'] ?? null;
@endphp
<article class="card stack" style="gap: 0.75rem;">
    <div>
        <h2 class="solicitud-section-title" style="margin: 0;">Oficio: descargar, firmar y subir</h2>
        <p class="field-hint" style="margin: 0.35rem 0 0;">
            1) Descarga el oficio en Word o PDF. 2) Fírmalo (manual o con certificado .p12 abajo).
            3) Sube el documento firmado. 4) Envía a Secretaría.
        </p>
    </div>

    <div class="row" style="gap: 0.5rem; flex-wrap: wrap;">
        <a href="{{ $oficioDescargarUrl }}" class="btn btn--secondary btn--sm" download>
            Descargar oficio (PDF si está disponible)
        </a>
        @if($oficioUsaDocx ?? false)
            <a href="{{ $oficioDocxUrl }}" class="btn btn--secondary btn--sm" download>
                Descargar Word (.docx)
            </a>
        @endif
        <a href="{{ $oficioPreviewUrl }}" class="btn btn--ghost btn--sm" target="_blank" rel="noopener">
            Ver vista previa
        </a>
    </div>

    @if($oficioFirmado)
        <p class="alert alert--success" style="margin: 0;">
            Oficio firmado cargado:
            <strong>{{ $oficioFirmado['nombre'] ?? 'Documento' }}</strong>
            @if(is_array($firmaMeta) && ($firmaMeta['tipo'] ?? '') === 'p12')
                (firma electrónica · {{ $firmaMeta['titular'] ?? 'certificado' }})
            @endif
            ·
            <a href="{{ SolicitudAdjuntos::urlVista($solicitud, $oficioFirmado['path']) }}" target="_blank" rel="noopener">Ver / descargar</a>
        </p>
    @endif

    @unless($soloDescarga ?? false)
        <p class="field-hint" style="margin: 0;">
            Para subir el oficio firmado a mano, usa el campo <strong>«Oficio firmado (PDF)»</strong> en el formulario de abajo.
        </p>
    @endunless

    <details class="stack" style="gap: 0.5rem;">
        <summary class="field__label" style="cursor: pointer;">Firmar electrónicamente con certificado .p12</summary>
        <p class="field-hint" style="margin: 0;">
            El archivo .p12 y su contraseña <strong>no se guardan</strong>; solo se usan en este paso para generar el PDF firmado.
        </p>
        <form method="POST" action="{{ route('solicitudes.firmar-oficio-p12', $solicitud) }}" enctype="multipart/form-data" class="stack" style="gap: 0.65rem;" data-no-loading>
            @csrf
            <label class="field">
                <span class="field__label">Certificado .p12 / .pfx</span>
                <input type="file" name="p12" class="field__input" accept=".p12,.pfx" required>
            </label>
            <label class="field">
                <span class="field__label">Contraseña del certificado</span>
                <input type="password" name="p12_password" class="field__input" required autocomplete="off">
            </label>
            <button type="submit" class="btn btn--primary btn--sm">Firmar y adjuntar oficio</button>
        </form>
    </details>
</article>
