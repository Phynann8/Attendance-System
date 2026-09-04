{{-- Custom pagination matching the application stylesheet (.pagination .page / .page.current) --}}
@php($elements = $elements ?? (method_exists($paginator, 'elements') ? $paginator->elements() : []))

@if($paginator->hasPages())
    <p class="summary">
        Showing
        @if($paginator->firstItem())
            {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }}
        @else
            0
        @endif
        of {{ $paginator->total() }} results
    </p>

    <div class="pages">
        @if($paginator->onFirstPage())
            <span class="page disabled" aria-disabled="true" aria-label="Previous">« Previous</span>
        @else
            <a class="page" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous">« Previous</a>
        @endif

        @foreach($elements as $element)
            {{-- "Three Dots" separator --}}
            @if(is_string($element))
                <span class="page disabled">{{ $element }}</span>
            @endif

            @if(is_array($element))
                @foreach($element as $page => $url)
                    @if($page == $paginator->currentPage())
                        <span class="page current" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="page" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if($paginator->hasMorePages())
            <a class="page" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next">Next »</a>
        @else
            <span class="page disabled" aria-disabled="true" aria-label="Next">Next »</span>
        @endif
    </div>
@endif