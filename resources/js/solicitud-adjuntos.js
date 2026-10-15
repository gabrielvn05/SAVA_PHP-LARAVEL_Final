const ALLOWED = /\.(pdf|png|jpe?g)$/i;

function formatBytes(bytes) {
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function fileKey(file) {
    return `${file.name}\0${file.size}\0${file.lastModified}`;
}

function filesToTransfer(files) {
    const dt = new DataTransfer();
    files.forEach((file) => dt.items.add(file));

    return dt;
}

function remainingExisting(root) {
    const base = parseInt(root.dataset.existingCount || '0', 10);
    const checked = root.querySelectorAll('[data-solicitud-adjuntos-remove-existing]:checked').length;

    return Math.max(0, base - checked);
}

function newFilesTotalBytes(files) {
    return files.reduce((sum, file) => sum + file.size, 0);
}

function showMessage(root, message) {
    const box = root.querySelector('[data-solicitud-adjuntos-message]');
    if (!box) {
        return;
    }
    box.textContent = message || '';
    box.hidden = !message;
}

function renderList(root, files, input, onRemove) {
    const list = root.querySelector('[data-solicitud-adjuntos-list]');
    if (!list) {
        return;
    }

    list.innerHTML = '';
    if (files.length === 0) {
        list.hidden = true;
        return;
    }

    list.hidden = false;
    files.forEach((file, index) => {
        const li = document.createElement('li');
        li.className = 'row';
        li.style.cssText = 'align-items: center; justify-content: space-between; gap: 0.5rem; margin: 0.25rem 0;';

        const label = document.createElement('span');
        label.textContent = `${file.name} (${formatBytes(file.size)})`;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn--secondary btn--sm';
        btn.textContent = 'Quitar';
        btn.addEventListener('click', () => onRemove(index));

        li.append(label, btn);
        list.append(li);
    });

    input.files = filesToTransfer(files).files;
}

function initSolicitudAdjuntosRoot(root) {
    const input = root.querySelector('[data-solicitud-adjuntos-input]');
    if (!input) {
        return;
    }

    const maxFiles = parseInt(root.dataset.maxFiles || '10', 10);
    const maxTotalBytes = parseInt(root.dataset.maxTotalBytes || String(10 * 1024 * 1024), 10);
    let selected = [...input.files];

    function updateRequired() {
        if (root.dataset.requiredWhen !== '1') {
            input.required = false;
            return;
        }
        input.required = selected.length + remainingExisting(root) < 1;
    }

    function syncUi() {
        renderList(root, selected, input, (index) => {
            selected = selected.filter((_, i) => i !== index);
            showMessage(root, '');
            syncUi();
        });
        updateRequired();
    }

    input.addEventListener('change', () => {
        const keys = new Set(selected.map(fileKey));
        let message = '';

        for (const file of input.files) {
            if (!ALLOWED.test(file.name)) {
                message = 'Solo se aceptan archivos PDF, PNG o JPG.';
                continue;
            }

            const key = fileKey(file);
            if (keys.has(key)) {
                continue;
            }

            if (selected.length + remainingExisting(root) >= maxFiles) {
                message = `Puedes adjuntar hasta ${maxFiles} archivos en total.`;
                break;
            }

            const next = [...selected, file];
            if (newFilesTotalBytes(next) > maxTotalBytes) {
                message = 'El peso total de los archivos adjuntos no puede superar 10 MB.';
                break;
            }

            selected = next;
            keys.add(key);
        }

        showMessage(root, message);
        syncUi();
        input.value = '';
    });

    root.querySelectorAll('[data-solicitud-adjuntos-remove-existing]').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            showMessage(root, '');
            updateRequired();
        });
    });

    const form = root.closest('form');
    form?.addEventListener('submit', (event) => {
        if (root.dataset.requiredWhen === '1' && selected.length + remainingExisting(root) < 1) {
            event.preventDefault();
            showMessage(root, 'Este tipo de trámite requiere adjuntar al menos un documento.');
            input.focus();
            return;
        }

        if (newFilesTotalBytes(selected) > maxTotalBytes) {
            event.preventDefault();
            showMessage(root, 'El peso total de los archivos adjuntos no puede superar 10 MB.');
        }
    });

    syncUi();
}

export function initSolicitudAdjuntos() {
    document.querySelectorAll('[data-solicitud-adjuntos]').forEach(initSolicitudAdjuntosRoot);
}
