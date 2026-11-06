async function loadPdfJs(root) {
    const libUrl = root.getAttribute('data-pdf-lib') || '/vendor/pdfjs/pdf.min.js';
    const workerUrl = root.getAttribute('data-pdf-worker') || '/vendor/pdfjs/pdf.worker.min.js';
    const pdfjsLib = await import(/* @vite-ignore */ libUrl);
    const api = pdfjsLib.default ?? pdfjsLib;
    api.GlobalWorkerOptions.workerSrc = workerUrl;

    return api;
}

function previewCssWidth(canvas) {
    const measured = canvas.parentElement?.clientWidth || 0;
    if (measured >= 160) {
        return measured;
    }

    return Math.max(240, Math.min(720, (window.innerWidth || 360) - 48));
}

async function paintPdfPage(page, canvas) {
    const base = page.getViewport({ scale: 1 });
    const cssWidth = previewCssWidth(canvas);
    const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
    const viewport = page.getViewport({ scale: (cssWidth / base.width) * pixelRatio });

    const cssHeight = Math.max(1, Math.round((cssWidth * base.height) / base.width));

    canvas.width = Math.floor(viewport.width);
    canvas.height = Math.floor(viewport.height);
    canvas.style.width = `${Math.round(cssWidth)}px`;
    canvas.style.height = `${cssHeight}px`;
    canvas.style.aspectRatio = `${Math.round(cssWidth)} / ${cssHeight}`;

    const context = canvas.getContext('2d');
    if (!context) {
        throw new Error('canvas');
    }

    await page.render({ canvasContext: context, viewport }).promise;
}

function watchPreviewWidth(canvas, render) {
    let lastWidth = canvas.parentElement?.clientWidth || 0;
    let timer = 0;

    window.addEventListener('resize', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => {
            const width = canvas.parentElement?.clientWidth || 0;
            if (width < 160 || Math.abs(width - lastWidth) < 32) {
                return;
            }
            lastWidth = width;
            render().catch(() => {});
        }, 200);
    });
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

function initP12PosicionFlow(root) {
    const pdfUrl = root.dataset.pdfUrl;
    const pageBtn = root.querySelector('[data-firma-page]');
    const pageCanvas = root.querySelector('[data-firma-page-canvas]');
    const mark = root.querySelector('[data-firma-mark]');
    const form = root.querySelector('[data-firma-form]');
    const pageInput = root.querySelector('[data-firma-page-input]');
    const xInput = root.querySelector('[data-firma-x-input]');
    const yInput = root.querySelector('[data-firma-y-input]');
    const statusEl = root.querySelector('[data-firma-status]');
    const p12FileInput = root.querySelector('[data-firma-p12-file]');
    const p12PasswordInput = root.querySelector('[data-firma-p12-password]');
    const passwordWrap = root.querySelector('[data-firma-p12-password-wrap]');
    const previewWrap = root.querySelector('[data-firma-p12-preview-wrap]');
    const submitBtn = root.querySelector('[data-firma-submit]');

    let currentPage = Number(pageInput?.value || 1);
    let pdfDoc = null;
    let painting = false;
    let painted = false;
    let posicionElegida = false;
    const tieneCoords = () => {
        const x = Number(xInput?.value || 0);
        const y = Number(yInput?.value || 0);
        return x > 0 && y > 0;
    };

    const setMark = (xPct, yPct) => {
        if (!mark) {
            return;
        }
        mark.hidden = false;
        mark.style.left = `${xPct}%`;
        mark.style.top = `${yPct}%`;
        posicionElegida = true;
        if (xInput) {
            xInput.value = String(Math.round(xPct * 10) / 10);
        }
        if (yInput) {
            yInput.value = String(Math.round(yPct * 10) / 10);
        }
        if (submitBtn instanceof HTMLButtonElement) {
            submitBtn.disabled = false;
        }
        if (statusEl) {
            statusEl.textContent = '';
        }
    };

    const syncSubmitState = () => {
        if (!(submitBtn instanceof HTMLButtonElement)) {
            return;
        }
        const p12Ok = p12FileInput instanceof HTMLInputElement && p12FileInput.files?.length;
        const passOk = p12PasswordInput instanceof HTMLInputElement && p12PasswordInput.value.trim() !== '';
        const listo = Boolean(p12Ok && passOk && (posicionElegida || tieneCoords()));
        if (submitBtn instanceof HTMLButtonElement) {
            submitBtn.disabled = !listo;
        }
    };

    p12FileInput?.addEventListener('change', () => {
        const hasFile = p12FileInput instanceof HTMLInputElement && p12FileInput.files?.length;
        if (passwordWrap instanceof HTMLElement) {
            passwordWrap.hidden = !hasFile;
        }
        if (!hasFile && previewWrap instanceof HTMLElement) {
            previewWrap.hidden = true;
        }
        if (hasFile && p12PasswordInput instanceof HTMLInputElement) {
            p12PasswordInput.required = true;
            p12PasswordInput.focus();
        }
        posicionElegida = false;
        syncSubmitState();
    });

    p12PasswordInput?.addEventListener('input', async () => {
        const passOk = p12PasswordInput instanceof HTMLInputElement && p12PasswordInput.value.trim() !== '';
        if (previewWrap instanceof HTMLElement) {
            previewWrap.hidden = !passOk;
        }
        if (passOk && !painted) {
            await renderPdfPreview();
        }
        syncSubmitState();
    });

    pageBtn?.addEventListener('click', (event) => {
        const rect = pageBtn.getBoundingClientRect();
        const xPct = ((event.clientX - rect.left) / rect.width) * 100;
        const yPct = ((event.clientY - rect.top) / rect.height) * 100;
        setMark(xPct, yPct);
    });

    form?.addEventListener('submit', (event) => {
        const p12Ok = p12FileInput instanceof HTMLInputElement && p12FileInput.files?.length;
        const passOk = p12PasswordInput instanceof HTMLInputElement && p12PasswordInput.value.trim() !== '';
        if (!p12Ok || !passOk || (!posicionElegida && !tieneCoords())) {
            event.preventDefault();
            if (statusEl) {
                statusEl.textContent = 'Sube el .p12, ingresa la contraseña y haz clic en el documento para ubicar la firma.';
            }
            return;
        }

        if (pageInput) {
            pageInput.value = String(currentPage);
        }
        if (submitBtn instanceof HTMLButtonElement) {
            submitBtn.disabled = true;
        }
    });

    syncSubmitState();
    if (pageCanvas) {
        watchPreviewWidth(pageCanvas, () => renderPdfPreview());
    }

    async function renderPdfPreview() {
        if (!pdfUrl || !pageCanvas || !pageBtn || painting) {
            return;
        }

        painting = true;
        try {
            if (!pdfDoc) {
                const pdfjs = await loadPdfJs(root);
                const response = await fetch(pdfUrl, { credentials: 'same-origin' });
                if (!response.ok) {
                    throw new Error('pdf');
                }
                const buffer = await response.arrayBuffer();
                pdfDoc = await pdfjs.getDocument({ data: buffer }).promise;
                currentPage = pdfDoc.numPages;
                if (pageInput) {
                    pageInput.value = String(currentPage);
                }
            }

            const page = await pdfDoc.getPage(currentPage);
            await paintPdfPage(page, pageCanvas);

            setMark(Number(xInput?.value || 75), Number(yInput?.value || 88));
            painted = true;
            syncSubmitState();
        } catch {
            if (!painted) {
                pdfDoc = null;
                if (statusEl) {
                    statusEl.textContent = 'No se pudo cargar la vista del PDF. Intenta de nuevo o usa la subida manual.';
                }
            }
        } finally {
            painting = false;
        }
    }
}

function initPadFlow(root) {
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
    if (!pad || !padCanvas) {
        return;
    }

    clearBtn?.addEventListener('click', () => pad.clear());

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
        if (pad.isEmpty()) {
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
            const pdfjs = await loadPdfJs(root);
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
            await paintPdfPage(page, pageCanvas);
            watchPreviewWidth(pageCanvas, async () => {
                const fresh = await pdf.getPage(currentPage);
                await paintPdfPage(fresh, pageCanvas);
                setMark(Number(xInput?.value || 75), Number(yInput?.value || 88));
            });

            setMark(Number(xInput?.value || 75), Number(yInput?.value || 88));
        } catch {
            if (statusEl) {
                statusEl.textContent = 'No se pudo cargar la vista del PDF.';
            }
        }
    })();
}

export function initOficioFirmaNavegador() {
    document.querySelectorAll('[data-oficio-firma-root]').forEach((root) => {
        if (!(root instanceof HTMLElement)) {
            return;
        }

        if (root.getAttribute('data-firma-modo') === 'p12-posicion') {
            initP12PosicionFlow(root);
            return;
        }

        initPadFlow(root);
    });
}
