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
    $frameSrc = $kind === 'pdf'
        ? $src.'#view=FitH&toolbar=1&navpanes=1'
        : $src;
@endphp

<div class="doc-viewer">
    <div class="doc-viewer__toolbar">
        <div class="doc-viewer__toolbar-left">
            <span class="doc-viewer__title">{{ $fileName }}</span>
        </div>
        <div class="doc-viewer__toolbar-right">
            <a
                href="{{ $downloadHref }}"
                class="doc-viewer__tool-btn"
                title="Descargar"
                @if(in_array($kind, ['pdf', 'image', 'docx'], true)) download="{{ $fileName }}" @endif
                target="_blank"
                rel="noopener"
            >⬇</a>
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
                <p>Vista previa no disponible para este formato. Use el botón de descarga del visor.</p>
                <a href="{{ $downloadHref }}" class="btn btn--secondary btn--sm" target="_blank" rel="noopener">Descargar archivo original</a>
            </div>
        @else
            <iframe title="{{ $title }}" src="{{ $frameSrc }}" class="doc-viewer__frame" loading="lazy"></iframe>
        @endif
    </div>
</div>
