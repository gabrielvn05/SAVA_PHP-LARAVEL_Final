async function loadPdfJs() {
    const pdfjsLib = await import(/* @vite-ignore */ '/vendor/pdfjs/pdf.min.js');
    const api = pdfjsLib.default ?? pdfjsLib;
    api.GlobalWorkerOptions.workerSrc = '/vendor/pdfjs/pdf.worker.min.js';

    return api;
}

function initSignaturePad(canvas) {
    const ctx = canvas.getContext('2d');
    if (!ctx) {
        return null;
    }

    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.strokeStyle = '#0c3d7a';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';

    let drawing = false;

    const pos = (event) => {
        const rect = canvas.getBoundingClientRect();
        const touch = event.touches?.[0];

        return {
            x: (touch?.clientX ?? event.clientX) - rect.left,
            y: (touch?.clientY ?? event.clientY) - rect.top,
        };
    };

    const start = (event) => {
        drawing = true;
        const p = pos(event);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
        event.preventDefault();
    };

    const move = (event) => {
        if (!drawing) {
            return;
        }
        const p = pos(event);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        event.preventDefault();
    };

    const end = () => {
        drawing = false;
    };

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    window.addEventListener('mouseup', end);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', end);

    return {
        clear() {
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        },
        isEmpty() {
            const data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
            for (let i = 0; i < data.length; i += 4) {
                if (data[i + 3] > 0 && (data[i] < 250 || data[i + 1] < 250 || data[i + 2] < 250)) {
                    return false;
                }
            }

            return true;
        },
        toBlob() {
            return new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        },
    };
}

export function initOficioFirmaNavegador() {
    const root = document.querySelector('[data-oficio-firma-root]');
    if (!root) {
        return;
    }

    const pdfUrl = root.dataset.pdfUrl;
    const pageBtn = root.querySelector('[data-firma-page]');
    const pageCanvas = root.querySelector('[data-firma-page-canvas]');
    const mark = root.querySelector('[data-firma-mark]');
    const padCanvas = root.querySelector('[data-firma-pad]');
    const clearBtn = root.querySelector('[data-firma-clear]');
    const form = root.querySelector('[data-firma-form]');
    const fileInput = root.querySelector('[data-firma-file-input]');
    const pageInput = root.querySelector('[data-firma-page-input]');
    const xInput = root.querySelector('[data-firma-x-input]');
    const yInput = root.querySelector('[data-firma-y-input]');
    const statusEl = root.querySelector('[data-firma-status]');

    const pad = padCanvas ? initSignaturePad(padCanvas) : null;

    clearBtn?.addEventListener('click', () => pad?.clear());

    let currentPage = Number(pageInput?.value || 1);

    const setMark = (xPct, yPct) => {
        if (!mark) {
            return;
        }
        mark.hidden = false;
        mark.style.left = `${xPct}%`;
        mark.style.top = `${yPct}%`;
        if (xInput) {
            xInput.value = String(Math.round(xPct * 10) / 10);
        }
        if (yInput) {
            yInput.value = String(Math.round(yPct * 10) / 10);
        }
    };

    pageBtn?.addEventListener('click', (event) => {
        const rect = pageBtn.getBoundingClientRect();
        const xPct = ((event.clientX - rect.left) / rect.width) * 100;
        const yPct = ((event.clientY - rect.top) / rect.height) * 100;
        setMark(xPct, yPct);
    });

    form?.addEventListener('submit', async (event) => {
        if (!pad || pad.isEmpty()) {
            event.preventDefault();
            if (statusEl) {
                statusEl.textContent = 'Dibuja tu firma en el recuadro antes de aplicar.';
            }
            return;
        }

        if (!fileInput) {
            return;
        }

        event.preventDefault();
        const blob = await pad.toBlob();
        if (!blob) {
            return;
        }

        const file = new File([blob], 'firma-navegador.png', { type: 'image/png' });
        const dt = new DataTransfer();
        dt.items.add(file);
        fileInput.files = dt.files;

        if (pageInput) {
            pageInput.value = String(currentPage);
        }

        form.submit();
    });

    if (!pdfUrl || !pageCanvas || !pageBtn) {
        return;
    }

    (async () => {
        try {
            const pdfjs = await loadPdfJs();
            const response = await fetch(pdfUrl, { credentials: 'same-origin' });
            if (!response.ok) {
                throw new Error('pdf');
            }
            const buffer = await response.arrayBuffer();
            const pdf = await pdfjs.getDocument({ data: buffer }).promise;
            currentPage = pdf.numPages;
            if (pageInput) {
                pageInput.value = String(currentPage);
            }

            const page = await pdf.getPage(currentPage);
            const viewport = page.getViewport({ scale: 1.35 });
            pageCanvas.width = viewport.width;
            pageCanvas.height = viewport.height;
            await page.render({ canvasContext: pageCanvas.getContext('2d'), viewport }).promise;

            setMark(Number(xInput?.value || 75), Number(yInput?.value || 88));
        } catch {
            if (statusEl) {
                statusEl.textContent = 'No se pudo cargar la vista del PDF. Usa descarga Word/PDF o certificado .p12.';
            }
        }
    })();
}
