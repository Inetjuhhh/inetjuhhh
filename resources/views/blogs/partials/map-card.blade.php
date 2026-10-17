{{-- A small example of the travel map with all pins, as one link to the real map (Reizen page, between
     the newest story and the others). The map is a picture here: it takes no clicks or drags. --}}
<a href="{{ route('blogs.map') }}"
   class="group relative isolate grid overflow-hidden rounded-2xl bg-night-700 ring-1 ring-white/5 transition hover:ring-brand/60 sm:grid-cols-5">
    <div class="flex flex-col justify-center p-6 sm:col-span-2">
        <p class="text-xs font-semibold uppercase tracking-widest text-accent"><i class="fas fa-map-marked-alt me-1"></i> Reiskaart</p>
        <p class="mt-2 font-display text-2xl leading-tight text-cream group-hover:text-brand transition">Zoeken op de kaart?</p>
        <p class="mt-2 text-cream-muted">Klik hier en kies een land of streek: dan zie je alle verhalen van daar.</p>
        <span class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-brand">
            Naar de kaart
            <svg class="h-3.5 w-3.5 transition-transform group-hover:translate-x-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 10"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 5h12m0 0L9 1m4 4L9 9"/></svg>
        </span>
    </div>
    {{-- The map's credit line (required by the tiles) stays visible --}}
    <div x-data="travelMap({ markers: @js($markers), mode: 'teaser' })" class="pointer-events-none relative isolate z-0 h-44 sm:col-span-3 sm:h-auto sm:min-h-[11rem]" aria-hidden="true">
        <div x-ref="map" class="absolute inset-0"></div>
    </div>
</a>
