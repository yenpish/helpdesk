@php
    $currentPage = $paginator->currentPage();
    $lastPage = $paginator->lastPage();
    $startPage = max(1, $currentPage - 2);
    $endPage = min($lastPage, $currentPage + 2);
@endphp
<div class="events-pagination">
    <span>Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }} results</span>
    <nav class="events-pagination-links" aria-label="{{ $ariaLabel ?? 'Results pages' }}">
        @if($paginator->onFirstPage())
            <span class="pagination-disabled" aria-disabled="true">Previous</span>
        @else
            <a class="pagination-link" href="{{ $paginator->previousPageUrl() }}">Previous</a>
        @endif

        @if($startPage > 1)
            <a class="pagination-link" href="{{ $paginator->url(1) }}" aria-label="Go to page 1">1</a>
            @if($startPage > 2)<span class="pagination-ellipsis" aria-hidden="true">…</span>@endif
        @endif
        @for($page = $startPage; $page <= $endPage; $page++)
            @if($page === $currentPage)
                <span class="pagination-link" aria-current="page">{{ $page }}</span>
            @else
                <a class="pagination-link" href="{{ $paginator->url($page) }}" aria-label="Go to page {{ $page }}">{{ $page }}</a>
            @endif
        @endfor
        @if($endPage < $lastPage)
            @if($endPage < $lastPage - 1)<span class="pagination-ellipsis" aria-hidden="true">…</span>@endif
            <a class="pagination-link" href="{{ $paginator->url($lastPage) }}" aria-label="Go to page {{ $lastPage }}">{{ $lastPage }}</a>
        @endif

        @if($paginator->hasMorePages())
            <a class="pagination-link" href="{{ $paginator->nextPageUrl() }}">Next</a>
        @else
            <span class="pagination-disabled" aria-disabled="true">Next</span>
        @endif
    </nav>
</div>
