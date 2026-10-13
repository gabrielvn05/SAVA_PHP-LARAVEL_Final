<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Oficio {{ $codigo }}</title>
    @if(!empty($oficioPreviewScriptUrl))
        <script type="module" src="{{ $oficioPreviewScriptUrl }}"></script>
    @elseif(app()->environment('local'))
        @vite(['resources/js/oficio-preview.js'])
    @endif
    <style>
        html, body { margin: 0; padding: 0; height: 100%; background: #e8eaed; font-family: "Segoe UI", system-ui, sans-serif; }
        .oficio-preview-root { min-height: 100%; box-sizing: border-box; padding: 12px; }
        .oficio-preview-status {
            font-size: 13px;
            color: #444;
            text-align: center;
            padding: 2rem 1rem;
            max-width: 36rem;
            margin: 0 auto;
        }
        .oficio-preview-status--error { color: #b42318; }
        .oficio-preview-actions { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; margin-top: 1rem; }
        .oficio-preview-actions a {
            display: inline-block;
            padding: 0.55rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
        }
        .oficio-preview-actions a.primary { background: #1e4f8a; color: #fff; }
        .oficio-preview-actions a.secondary { background: #fff; color: #1e4f8a; border: 1px solid #c5d4e8; }
        .docx-wrapper { background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.12); margin: 0 auto; }
    </style>
</head>
<body
    data-oficio-docx-url="{{ $docxUrl }}"
    data-oficio-codigo="{{ $codigo }}"
>
    <div class="oficio-preview-root">
        @if(empty($oficioPreviewScriptUrl) && ! app()->environment('local'))
            <div class="oficio-preview-status">
                <p style="margin: 0 0 0.75rem;">
                    La vista previa embebida no está disponible en este servidor.
                    Descargue el oficio en formato Word (idéntico a la plantilla institucional).
                </p>
                @if(!empty($pdfNoDisponible))
                    <p class="field-hint" style="margin: 0 0 0.75rem; font-size: 12px; color: #666;">
                        Para habilitar vista PDF instale LibreOffice y configure <code>OFICIO_LIBREOFFICE_PATH</code> en el servidor.
                    </p>
                @endif
                <div class="oficio-preview-actions">
                    <a class="primary" href="{{ $descargarUrl }}">Descargar Word (.docx)</a>
                    <a class="secondary" href="{{ $docxUrl }}" target="_blank" rel="noopener">Abrir documento</a>
                </div>
            </div>
        @else
            @if(!empty($avisoAproximado))
                <p class="oficio-preview-status" style="padding-bottom: 0.5rem;">
                    Vista previa aproximada. Use <strong>Descargar</strong> en la ficha para el documento oficial.
                </p>
            @endif
            <p class="oficio-preview-status" id="oficio-preview-status">Cargando documento…</p>
            <div id="oficio-preview-container" hidden></div>
        @endif
    </div>
</body>
</html>
