<x-app-layout title="Reiskaart">
    <x-slot name="header">
        <p class="text-xs uppercase tracking-widest font-semibold text-accent">Reiskaart</p>
        <h1 class="mt-2 font-display text-4xl sm:text-5xl text-cream">Waar wil je naartoe?</h1>
        <p class="mt-3 max-w-2xl text-cream-muted">
            Elk verhaal staat op de plek waar het zich afspeelt. Zoom in op een gebied en je ziet meteen welke verhalen daar spelen.
        </p>
    </x-slot>

    <div x-data="travelMap({ markers: @js($markers), mode: 'full', trip: @js($activeTrip) })"
         class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 grid gap-6 lg:grid-cols-3">

        {{-- Filter the map on one trip --}}
        @if($trips->isNotEmpty())
            <div class="lg:col-span-3 flex flex-wrap items-center gap-2">
                <span class="me-1 text-xs font-semibold uppercase tracking-widest text-cream-muted">Reizen</span>
                <button type="button" x-on:click="activeTrip && selectTrip(activeTrip)"
                        class="pill px-3.5 py-1.5 text-sm transition"
                        x-bind:class="activeTrip ? 'bg-night-700 text-cream-muted hover:text-cream' : 'bg-brand text-night-900'">
                    Alle verhalen
                </button>
                @foreach($trips as $mapTrip)
                    <button type="button" x-on:click="selectTrip(@js($mapTrip->slug))"
                            class="pill px-3.5 py-1.5 text-sm transition"
                            x-bind:class="activeTrip === @js($mapTrip->slug) ? 'bg-brand text-night-900' : 'bg-night-700 text-cream-muted hover:text-cream'">
                        🧭 {{ $mapTrip->name }}
                    </button>
                @endforeach

                @foreach($trips as $mapTrip)
                    <a href="{{ $mapTrip->filterUrl() }}" x-show="activeTrip === @js($mapTrip->slug)" x-cloak
                       class="ms-auto text-sm text-brand hover:underline underline-offset-4">
                        Lees alle delen op volgorde →
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Map --}}
        <div class="relative isolate lg:col-span-2 h-[60vh] min-h-[22rem] lg:h-[calc(100vh-12rem)] lg:sticky lg:top-24 overflow-hidden rounded-3xl shadow-soft ring-1 ring-white/5">
            <div x-ref="map" class="h-full w-full bg-night-900"></div>

            <button type="button" x-on:click="map?.showAll()"
                    class="absolute top-4 right-4 z-[500] inline-flex items-center gap-2 rounded-full bg-night-900/85 px-4 py-2 text-sm text-cream backdrop-blur hover:bg-brand hover:text-night-900 transition">
                <i class="fas fa-globe-europe"></i> Toon alles
            </button>
        </div>

        {{-- Stories in the current map view --}}
        <aside class="lg:col-span-1">
            <p class="text-sm text-cream-muted">
                <span class="font-semibold text-cream" x-text="visible.length"></span>
                <span x-text="visible.length === 1 ? 'verhaal' : 'verhalen'"></span> in dit gebied
            </p>

            <ul class="mt-4 space-y-3">
                <template x-for="marker in visible" :key="marker.id">
                    <li x-on:mouseenter="map?.highlight(marker.id, true)" x-on:mouseleave="map?.highlight(marker.id, false)">
                        <a :href="marker.url"
                           class="group flex gap-4 rounded-2xl bg-night-700 p-3 ring-1 ring-white/5 hover:bg-night-600 hover:ring-brand/40 transition">
                            <img :src="marker.cover" alt="" loading="lazy" class="h-20 w-24 flex-none rounded-xl object-cover">
                            <span class="min-w-0">
                                <span class="block text-xs font-semibold uppercase tracking-widest text-accent truncate" x-text="marker.place"></span>
                                <span class="mt-1 block font-display text-lg leading-snug text-cream group-hover:text-brand transition line-clamp-2" x-text="marker.title"></span>
                                <span class="mt-1 block text-xs text-cream-muted" x-text="marker.date"></span>
                            </span>
                        </a>
                        <button type="button" x-on:click="map?.focus(marker.id); $refs.map.scrollIntoView({ behavior: 'smooth', block: 'center' })"
                                class="mt-1 ms-3 text-xs text-brand hover:underline underline-offset-4">
                            <i class="fas fa-map-marker-alt me-1"></i>Toon op de kaart
                        </button>
                    </li>
                </template>
            </ul>

            <p x-show="visible.length === 0" class="mt-6 rounded-2xl bg-night-700 p-5 text-cream-muted">
                Hier heb ik (nog) niet over geschreven. Zoom uit of klik op <span class="text-cream">Toon alles</span>.
            </p>
        </aside>
    </div>
</x-app-layout>
