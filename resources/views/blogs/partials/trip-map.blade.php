{{-- The trip so far: every published part on the map, growing with each new story --}}
<section x-data x-intersect.once="$el.classList.add('is-visible')"
         class="reveal mt-16 overflow-hidden rounded-3xl bg-night-700 shadow-soft ring-1 ring-white/5">
    <div class="flex flex-col gap-2 p-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-accent">
                <i class="fas fa-route me-1"></i> De route tot nu toe
            </p>
            <h2 class="mt-1 font-display text-2xl text-cream">{{ $trip->name }}</h2>
            <p class="text-sm text-cream-muted">
                {{ $markers->count() }} {{ $markers->count() === 1 ? 'stop' : 'stops' }} op de kaart{{ $trip->period() ? ' · ' . $trip->period() : '' }}
            </p>
        </div>
        <a href="{{ $trip->mapUrl() }}" class="text-sm text-brand hover:underline underline-offset-4">
            Bekijk op de reiskaart →
        </a>
    </div>

    <div x-data="tripMap(@js($markers))" x-intersect.once.margin.200px="load()"
         class="relative isolate h-72 sm:h-96">
        <div x-ref="map" class="absolute inset-0"></div>
    </div>
</section>
