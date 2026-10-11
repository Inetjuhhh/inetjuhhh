@php
    $parts = $trip->parts();
    $position = $parts->search(fn ($part) => $part->is($blog));
@endphp

{{-- Where this story sits in its trip, with quick links to the other parts --}}
<nav aria-label="Delen van deze reis"
     class="mt-10 flex flex-col gap-4 rounded-2xl bg-night-700 p-5 ring-1 ring-white/5 sm:flex-row sm:items-center sm:justify-between">
    <div>
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
        <div class="flex flex-wrap items-center gap-2">
            @foreach($parts as $i => $part)
                @if($part->is($blog))
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-accent font-semibold text-night-900"
                          aria-current="page" title="{{ $part->title }}">{{ $i + 1 }}</span>
                @else
                    <a href="{{ route('blogs.show', $part) }}" title="{{ $part->title }}"
                       class="flex h-9 w-9 items-center justify-center rounded-full bg-night-600 text-cream-muted transition hover:bg-brand hover:text-night-900">
                        {{ $i + 1 }}
                    </a>
                @endif
            @endforeach
            <a href="{{ $trip->filterUrl() }}" class="ms-1 text-sm text-brand hover:underline underline-offset-4">Alle delen</a>
        </div>
    @endif
</nav>
