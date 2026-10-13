{{-- Pagination as a route: the pages are stops, like the trip map (see the route-stops component) --}}
@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $arrow = 'flex h-10 w-10 flex-none items-center justify-center rounded-full transition';

        $stops = [];
        foreach ($elements as $element) {
            if (is_string($element)) {
                $stops[] = ['gap' => true];
                continue;
            }
            foreach ($element as $page => $url) {
                $stops[] = [
                    'number' => $page,
                    'url' => $url,
                    'label' => "Pagina {$page}",
                    'state' => $page < $current ? 'passed' : ($page == $current ? 'current' : 'ahead'),
                    // On a phone only the stops next to this page fit; the road still runs on at both ends
                    'far' => abs($page - $current) > 1,
                ];
            }
        }
    @endphp
    <nav role="navigation" aria-label="Paginering" class="flex flex-col items-center gap-4">
        <div class="flex items-center">
            @if ($paginator->onFirstPage())
                <span class="{{ $arrow }} text-cream-muted/30" aria-disabled="true"><i class="fas fa-chevron-left text-sm"></i></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Vorige pagina" class="{{ $arrow }} text-cream-muted hover:bg-night-700 hover:text-cream"><i class="fas fa-chevron-left text-sm"></i></a>
            @endif

            <x-route-stops :stops="$stops" />

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Volgende pagina" class="{{ $arrow }} text-cream-muted hover:bg-night-700 hover:text-cream"><i class="fas fa-chevron-right text-sm"></i></a>
            @else
                <span class="{{ $arrow }} text-cream-muted/30" aria-disabled="true"><i class="fas fa-chevron-right text-sm"></i></span>
            @endif
        </div>
        <p class="text-xs font-semibold uppercase tracking-widest text-cream-muted">
            Stop {{ $current }} van {{ $paginator->lastPage() }} · verhaal {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} van {{ $paginator->total() }}
        </p>
    </nav>
@endif
