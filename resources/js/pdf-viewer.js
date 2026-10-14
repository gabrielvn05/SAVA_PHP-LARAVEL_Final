async function loadPdfJs(root) {
    const libUrl = root.getAttribute('data-pdf-lib') || '/vendor/pdfjs/pdf.min.js';
    const workerUrl = root.getAttribute('data-pdf-worker') || '/vendor/pdfjs/pdf.worker.min.js';
    const pdfjsLib = await import(/* @vite-ignore */ libUrl);
    const api = pdfjsLib.default ?? pdfjsLib;
    api.GlobalWorkerOptions.workerSrc = workerUrl;

    return api;
}

export function initPdfViewers(nodes) {
    nodes.forEach((node) => {
        renderPdfViewer(node).catch((error) => console.error(error));
    });
}

async function renderPdfViewer(root) {
    const url = root.getAttribute('data-src');
    const pagesEl = root.querySelector('[data-pdf-pages]');
    const statusEl = root.querySelector('[data-pdf-status]');
    const labelEl = root.querySelector('[data-pdf-label]');
    const prevBtn = root.querySelector('[data-pdf-prev]');
    const nextBtn = root.querySelector('[data-pdf-next]');
    const zoomInBtn = root.querySelector('[data-pdf-zoom-in]');
    const zoomOutBtn = root.querySelector('[data-pdf-zoom-out]');
    const fallbackEl = root.querySelector('[data-pdf-fallback]');

    if (!url || !pagesEl) {
        return;
    }

    const showError = (message) => {
        if (statusEl) {
            statusEl.hidden = false;
            statusEl.textContent = message;
            statusEl.classList.add('doc-viewer__status--error');
        }
        if (fallbackEl) {
            fallbackEl.hidden = false;
        }
        pagesEl.hidden = true;
    };

    let pdfjsLib;
    try {
        pdfjsLib = await loadPdfJs(root);
    } catch (error) {
        console.error(error);
        showError('No se pudo cargar el visor PDF. Ábralo o descárguelo con los botones de arriba.');
        return;
    }

    let pdf;
    try {
        const response = await fetch(url, { credentials: 'same-origin' });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const data = await response.arrayBuffer();
        pdf = await pdfjsLib.getDocument({ data }).promise;
    } catch (error) {
        console.error(error);
        showError('Este navegador no pudo mostrar el PDF embebido. Ábralo o descárguelo con los botones de arriba.');
        return;
    }

    let zoom = 1;
    let currentPage = 1;
    const canvases = [];
    const observer = 'IntersectionObserver' in window
        ? new IntersectionObserver((entries) => {
            const visible = entries
                .filter((entry) => entry.isIntersecting)
                .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
            if (!visible) {
                return;
            }
            const number = Number(visible.target.getAttribute('data-pdf-page-canvas'));
            if (Number.isFinite(number) && number > 0) {
                currentPage = number;
                updateButtons();
            }
        }, { root: pagesEl, threshold: 0.45 })
        : null;

    const availableWidth = () => Math.max(260, (pagesEl.clientWidth || root.clientWidth || window.innerWidth - 32) - 8);

    const updateButtons = () => {
        if (prevBtn) prevBtn.disabled = currentPage <= 1;
        if (nextBtn) nextBtn.disabled = currentPage >= pdf.numPages;
        if (labelEl) labelEl.textContent = `${currentPage} / ${pdf.numPages}`;
    };

    const renderPages = async () => {
        pagesEl.hidden = false;
        pagesEl.replaceChildren();
        canvases.length = 0;
        const targetWidth = availableWidth();

        for (let number = 1; number <= pdf.numPages; number += 1) {
            const page = await pdf.getPage(number);
            const base = page.getViewport({ scale: 1 });
            const viewport = page.getViewport({ scale: (targetWidth / base.width) * zoom });
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d', { alpha: false });
            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            canvas.className = 'pdf-viewer__page';
            canvas.setAttribute('data-pdf-page-canvas', String(number));
            pagesEl.appendChild(canvas);
            canvases.push(canvas);
            observer?.observe(canvas);
            await page.render({ canvasContext: context, viewport }).promise;
        }

        if (statusEl) {
            statusEl.hidden = true;
        }
        updateButtons();
    };

    const goToPage = (number) => {
        currentPage = Math.min(pdf.numPages, Math.max(1, number));
        canvases[currentPage - 1]?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        updateButtons();
    };

    prevBtn?.addEventListener('click', () => goToPage(currentPage - 1));
    nextBtn?.addEventListener('click', () => goToPage(currentPage + 1));
    zoomInBtn?.addEventListener('click', async () => {
        zoom = Math.min(2.4, Number((zoom + 0.2).toFixed(1)));
        await renderPages();
        goToPage(currentPage);
    });
    zoomOutBtn?.addEventListener('click', async () => {
        zoom = Math.max(0.7, Number((zoom - 0.2).toFixed(1)));
        await renderPages();
        goToPage(currentPage);
    });

    let resizeTimer = 0;
    window.addEventListener('resize', () => {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(() => {
            renderPages().catch((error) => console.error(error));
        }, 250);
    });

    await renderPages();
}
