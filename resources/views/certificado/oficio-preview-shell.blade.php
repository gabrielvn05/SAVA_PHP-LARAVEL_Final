<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Oficio {{ $codigo }}</title>
    <style>
        html, body { margin: 0; padding: 0; min-height: 100%; background: #e8eaed; }
        .oficio-preview-root { min-height: 100%; box-sizing: border-box; padding: 12px; }
        .oficio-preview-status {
            font-size: 13px;
            color: #444;
            text-align: center;
            padding: 1.25rem 1rem;
            max-width: 36rem;
            margin: 0 auto;
        }
        .oficio-preview-status--error { color: #b42318; }
        .docx-wrapper { background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.12); margin: 0 auto; max-width: 100%; }
        #oficio-preview-container { min-height: 70vh; }
        @media (max-width: 767px) {
            .oficio-preview-root { padding: 8px; }
            .docx-wrapper { width: 100% !important; }
        }
    </style>
</head>
<body
    data-oficio-docx-url="{{ $docxUrl }}"
    data-oficio-html-url="{{ $htmlFallbackUrl ?? '' }}"
    data-oficio-codigo="{{ $codigo }}"
>
    <div class="oficio-preview-root">
        <p class="oficio-preview-status" id="oficio-preview-status">Cargando documento original…</p>
        <div id="oficio-preview-container" hidden></div>
    </div>
    <script src="{{ asset('vendor/docx-preview/jszip.min.js') }}"></script>
    <script src="{{ asset('vendor/docx-preview/docx-preview.min.js') }}"></script>
    <script>
        (function () {
            const url = document.body.dataset.oficioDocxUrl;
            const htmlUrl = document.body.dataset.oficioHtmlUrl;
            const status = document.getElementById('oficio-preview-status');
            const container = document.getElementById('oficio-preview-container');

            function showHtmlFallback() {
                if (!htmlUrl || !container) return;
                container.hidden = false;
                container.innerHTML = '';
                const frame = document.createElement('iframe');
                frame.title = 'Oficio';
                frame.src = htmlUrl;
                frame.style.cssText = 'width:100%;min-height:75vh;border:0;background:#fff;';
                container.appendChild(frame);
            }

            async function init() {
                if (!url || !status || !container || !window.docx || typeof window.docx.renderAsync !== 'function') {
                    throw new Error('Visor DOCX no disponible');
                }

                const response = await fetch(url, { credentials: 'same-origin' });
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                const blob = await response.blob();
                container.hidden = false;
                status.hidden = true;
                await window.docx.renderAsync(blob, container, null, {
                    className: 'docx',
                    inWrapper: true,
                    ignoreWidth: window.innerWidth < 768,
                    ignoreHeight: false,
                    breakPages: true,
                    ignoreLastRenderedPageBreak: true,
                    useBase64URL: true
                });
            }

            init().catch(function (error) {
                console.error(error);
                if (htmlUrl) {
                    status.textContent = 'Mostrando el oficio con sello institucional…';
                    showHtmlFallback();
                    return;
                }
                status.textContent = 'No se pudo mostrar el documento original. Usa Descargar para abrir el archivo Word.';
                status.classList.add('oficio-preview-status--error');
            });
        })();
    </script>
</body>
</html>
