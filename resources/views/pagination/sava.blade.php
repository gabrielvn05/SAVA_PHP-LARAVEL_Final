@if ($paginator->hasPages())
    <nav class="pagination" role="navigation" aria-label="Paginación">
        <p class="pagination__summary">
            @if ($paginator->firstItem())
                Mostrando {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}
            @else
                {{ $paginator->count() }} resultados
            @endif
        </p>

        <div class="pagination__actions">
            @if ($paginator->onFirstPage())
                <span class="pagination__btn pagination__btn--disabled" aria-disabled="true">Anterior</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="pagination__btn" rel="prev">Anterior</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pagination__ellipsis" aria-hidden="true">…</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pagination__btn pagination__btn--current" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="pagination__btn">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="pagination__btn" rel="next">Siguiente</a>
            @else
                <span class="pagination__btn pagination__btn--disabled" aria-disabled="true">Siguiente</span>
            @endif
        </div>
    </nav>
@endif
