@php
    $parts = $trip->parts();
    $position = $parts->search(fn ($part) => $part->is($blog));
    $previous = $position !== false && $position > 0 ? $parts[$position - 1] : null;
    $next = $position !== false ? $parts->get($position + 1) : null;
    $arrow = 'flex h-10 w-10 flex-none items-center justify-center rounded-full transition';
    // A long trip doesn't fit next to the name: then the route goes underneath, with shorter roads
    $long = $parts->count() > 6;

    // The parts as stops on a route, the same as the pagination under the blog overviews
    $stops = $parts->map(fn ($part, $i) => [
        'number' => $i + 1,
        'url' => route('blogs.show', $part),
        'label' => 'Deel ' . ($i + 1) . ': ' . $part->title,
        'state' => $position === false || $i > $position ? 'ahead' : ($i === $position ? 'current' : 'passed'),
        // A long trip doesn't fit on a phone: there only the parts around this one show
        'far' => $parts->count() > 5 && $position !== false && abs($i - $position) > 1,
    ])->all();
@endphp

{{-- Where this story sits in its trip, with the route to the other parts --}}
<nav aria-label="Delen van deze reis"
     @class([
         'mt-10 flex flex-col gap-4 rounded-2xl bg-night-700 p-5 ring-1 ring-white/5',
         'sm:flex-row sm:items-center sm:justify-between' => ! $long,
     ])>
    <div class="min-w-0">
        <p class="text-xs font-semibold uppercase tracking-widest text-accent">
            🧭 Reis{{ $position !== false ? ' · deel ' . ($position + 1) . ' van ' . $parts->count() : '' }}
        </p>
        <a href="{{ $trip->filterUrl() }}" class="mt-1 block font-display text-xl text-cream hover:text-brand transition">
            {{ $trip->name }}
        </a>
        @if($trip->period())
            <p class="text-sm text-cream-muted">{{ $trip->period() }}</p>
        @endif
    </div>

    @if($parts->count() > 1)
        <div @class(['flex flex-col items-center gap-2', 'sm:items-end' => ! $long])>
            <div class="flex items-center">
                @if($previous)
                    <a href="{{ route('blogs.show', $previous) }}" rel="prev" aria-label="Vorig deel: {{ $previous->title }}" title="{{ $previous->title }}"
                       class="{{ $arrow }} text-cream-muted hover:bg-night-600 hover:text-cream"><i class="fas fa-chevron-left text-sm"></i></a>
                @else
                    <span class="{{ $arrow }} text-cream-muted/30" aria-disabled="true"><i class="fas fa-chevron-left text-sm"></i></span>
                @endif

                <x-route-stops :stops="$stops" :compact="$long" />

                @if($next)
                    <a href="{{ route('blogs.show', $next) }}" rel="next" aria-label="Volgend deel: {{ $next->title }}" title="{{ $next->title }}"
                       class="{{ $arrow }} text-cream-muted hover:bg-night-600 hover:text-cream"><i class="fas fa-chevron-right text-sm"></i></a>
                @else
                    <span class="{{ $arrow }} text-cream-muted/30" aria-disabled="true"><i class="fas fa-chevron-right text-sm"></i></span>
                @endif
            </div>
            <a href="{{ $trip->filterUrl() }}" class="text-sm text-brand hover:underline underline-offset-4">Alle delen</a>
        </div>
    @endif
</nav>
