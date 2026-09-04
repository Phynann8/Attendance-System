{{-- Custom simple pagination (prev / current / next) matching the application stylesheet --}}
@if($paginator->hasPages())
    <div class="pages">
        @if($paginator->onFirstPage())
            <span class="page disabled" aria-disabled="true" aria-label="Previous">« Previous</span>
        @else
            <a class="page" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous">« Previous</a>
        @endif

        <span class="page current" aria-current="page">{{ $paginator->currentPage() }}</span>

        @if($paginator->hasMorePages())
            <a class="page" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next">Next »</a>
        @else
            <span class="page disabled" aria-disabled="true" aria-label="Next">Next »</span>
        @endif
    </div>
@endif