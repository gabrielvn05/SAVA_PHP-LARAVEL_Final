import { renderAsync } from 'docx-preview';

async function initOficioPreview() {
    const url = document.body.dataset.oficioDocxUrl;
    const status = document.getElementById('oficio-preview-status');
    const container = document.getElementById('oficio-preview-container');

    if (!url || !status || !container) {
        return;
    }

    try {
        const response = await fetch(url, { credentials: 'same-origin' });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const blob = await response.blob();
        container.hidden = false;
        status.hidden = true;

        await renderAsync(blob, container, null, {
            className: 'docx',
            inWrapper: true,
            ignoreWidth: false,
            ignoreHeight: false,
            breakPages: true,
            useBase64URL: true,
        });
    } catch (error) {
        status.textContent = 'No se pudo cargar la vista previa del oficio. Use descargar para abrir el archivo Word.';
        status.classList.add('oficio-preview-status--error');
        console.error(error);
    }
}

initOficioPreview();
