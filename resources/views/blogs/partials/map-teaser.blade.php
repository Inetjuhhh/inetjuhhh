@php
    $countryCount = $markers->pluck('country')
        ->flatMap(fn ($countries) => explode(', ', $countries))
        ->filter()
        ->unique()
        ->count();
@endphp

<section x-data x-intersect.once="$el.classList.add('is-visible')"
         class="reveal mb-14 grid overflow-hidden rounded-3xl bg-night-700 shadow-soft ring-1 ring-white/5 lg:grid-cols-5">
    <div class="flex flex-col justify-center p-8 lg:col-span-2 lg:p-10">
        <p class="text-xs font-semibold uppercase tracking-widest text-accent">
            <i class="fas fa-map-marked-alt me-1"></i> Reiskaart
        </p>
        <h2 class="mt-2 font-display text-3xl leading-tight text-cream lg:text-4xl">Zoek je verhaal op de kaart</h2>
        <p class="mt-4 text-cream-muted">
            Weet je al waarover je wilt lezen? Zoom in op een land of streek en lees wat ik daar heb meegemaakt.
        </p>

        <dl class="mt-6 flex gap-8">
            <div>
                <dt class="text-xs uppercase tracking-widest text-cream-muted">Verhalen</dt>
                <dd class="font-display text-3xl text-cream">{{ $markers->count() }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-widest text-cream-muted">Landen</dt>
                <dd class="font-display text-3xl text-cream">{{ $countryCount }}</dd>
            </div>
        </dl>

        <a href="{{ route('blogs.map') }}"
           class="group mt-8 inline-flex items-center gap-2 self-start rounded-full bg-brand px-5 py-2.5 font-semibold text-night-900 transition hover:bg-cream">
            Open de reiskaart
            <svg class="h-3.5 w-3.5 transition-transform group-hover:translate-x-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 10">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 5h12m0 0L9 1m4 4L9 9"/>
            </svg>
        </a>
    </div>

    <div x-data="travelMap({ markers: @js($markers), mode: 'teaser', planned: @js(App\Support\SiteCache::plannedRoutes()) })"
         class="relative isolate h-72 sm:h-80 lg:col-span-3 lg:h-auto lg:min-h-[24rem]">
        <div x-ref="map" class="absolute inset-0"></div>
    </div>
</section>
