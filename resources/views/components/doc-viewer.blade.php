@props([
    'title' => 'Documento',
    'fileName' => null,
    'src',
    'downloadHref' => null,
    'kind' => 'pdf',
])

@php
    $fileName ??= $title;
    $downloadHref ??= $src;
    $isPdf = $kind === 'pdf';
    $frameSrc = $isPdf
        ? $src.'#view=FitH&toolbar=1&navpanes=0'
        : $src;
@endphp

<div class="doc-viewer" data-doc-viewer data-kind="{{ $kind }}" aria-label="{{ $title }}">
    <div class="doc-viewer__toolbar">
        <div class="doc-viewer__toolbar-left">
            <span class="doc-viewer__title">{{ $fileName }}</span>
        </div>
        <div class="doc-viewer__toolbar-right">
            <a
                href="{{ $src }}"
                class="doc-viewer__tool-btn doc-viewer__tool-btn--text"
                title="Abrir en el visor del teléfono"
                target="_blank"
                rel="noopener"
            >Abrir</a>
            <a
                href="{{ $downloadHref }}"
                class="doc-viewer__tool-btn doc-viewer__tool-btn--text"
                title="Descargar {{ $fileName }}"
                @if($kind === 'image') download="{{ $fileName }}" @endif
            >Descargar</a>
            <button type="button" class="doc-viewer__tool-btn" title="Abrir / imprimir" data-doc-print="{{ $src }}">🖨</button>
        </div>
    </div>
    <div class="doc-viewer__canvas">
        @if($kind === 'image')
            <div class="doc-viewer__image-wrap">
                <img src="{{ $src }}" alt="{{ $fileName }}" class="doc-viewer__image">
            </div>
        @elseif($kind === 'other')
            <div class="doc-viewer__status">
                <p>Vista previa no disponible para este formato. Ábralo o descárguelo con los botones del visor.</p>
                <a href="{{ $downloadHref }}" class="btn btn--secondary btn--sm" target="_blank" rel="noopener">Descargar archivo original</a>
            </div>
        @elseif($isPdf)
            <iframe title="{{ $title }}" src="{{ $frameSrc }}" class="doc-viewer__frame doc-viewer__frame--native" loading="lazy"></iframe>
            <div
                class="pdf-viewer"
                data-pdf-viewer
                data-src="{{ $src }}"
                data-pdf-lib="{{ asset('vendor/pdfjs/pdf.min.js') }}"
                data-pdf-worker="{{ asset('vendor/pdfjs/pdf.worker.min.js') }}"
            >
                <div class="pdf-viewer__bar">
                    <button type="button" class="pdf-viewer__ctrl" data-pdf-prev aria-label="Página anterior">‹</button>
                    <span class="pdf-viewer__label" data-pdf-label>Cargando…</span>
                    <button type="button" class="pdf-viewer__ctrl" data-pdf-next aria-label="Página siguiente">›</button>
                    <button type="button" class="pdf-viewer__ctrl" data-pdf-zoom-out aria-label="Alejar">−</button>
                    <button type="button" class="pdf-viewer__ctrl" data-pdf-zoom-in aria-label="Acercar">+</button>
                </div>
                <div class="pdf-viewer__pages" data-pdf-pages></div>
                <p class="doc-viewer__status" data-pdf-status>Cargando documento…</p>
                <div class="pdf-viewer__fallback" data-pdf-fallback hidden>
                    <a href="{{ $src }}" class="btn btn--primary btn--sm" target="_blank" rel="noopener">Abrir PDF</a>
                    <a href="{{ $downloadHref }}" class="btn btn--secondary btn--sm" target="_blank" rel="noopener">Descargar</a>
                </div>
            </div>
        @else
            <iframe title="{{ $title }}" src="{{ $frameSrc }}" class="doc-viewer__frame" loading="lazy"></iframe>
        @endif
    </div>
</div>
