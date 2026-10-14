@if ($paginator->hasPages())
    <nav class="pagination" role="navigation" aria-label="Paginación">
        <p class="pagination__summary">Página {{ $paginator->currentPage() }}</p>
        <div class="pagination__actions">
            @if ($paginator->onFirstPage())
                <span class="pagination__btn pagination__btn--disabled" aria-disabled="true">Anterior</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="pagination__btn" rel="prev">Anterior</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="pagination__btn" rel="next">Siguiente</a>
            @else
                <span class="pagination__btn pagination__btn--disabled" aria-disabled="true">Siguiente</span>
            @endif
        </div>
    </nav>
@endif
