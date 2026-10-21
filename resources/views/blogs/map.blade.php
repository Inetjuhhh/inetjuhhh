<x-app-layout title="Reiskaart">
    <x-slot name="header">
        <p class="text-xs uppercase tracking-widest font-semibold text-accent">Reiskaart</p>
        <h1 class="mt-2 font-display text-4xl sm:text-5xl text-cream">Waar wil je met me naartoe?</h1>
        <p class="mt-3 max-w-2xl text-cream-muted">
            Elke blog speelt zich af op een locatie. Zoom in op een gebied en je ziet meteen welke blogs daarvan te vinden zijn :).
        </p>
    </x-slot>

    <div x-data="travelMap({ markers: @js($markers), mode: 'full', trip: @js($activeTrip), planned: @js(App\Support\SiteCache::plannedRoutes()) })"
         class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 grid grid-cols-1 gap-6 lg:grid-cols-3">

        <div class="min-w-0 space-y-6 lg:col-span-2">
            {{-- Map --}}
            <div class="relative isolate h-[60vh] min-h-[22rem] lg:h-[calc(100vh-20rem)] overflow-hidden rounded-3xl shadow-soft ring-1 ring-white/5">
                <div x-ref="map" class="h-full w-full bg-night-900"></div>

                <button type="button" x-on:click="map?.showAll()"
                        class="absolute top-4 right-4 z-[500] inline-flex items-center gap-2 rounded-full bg-night-900/85 px-4 py-2 text-sm text-cream backdrop-blur hover:bg-brand hover:text-night-900 transition">
                    <i class="fas fa-globe-europe"></i> Toon alles
                </button>
            </div>

            {{-- Filter the map on one trip --}}
            @if($trips->isNotEmpty())
                <div class="min-w-0">
                    <div class="-mx-4 flex items-center gap-2 overflow-x-auto no-scrollbar px-4 [mask-image:linear-gradient(to_right,black_85%,transparent)] sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:[mask-image:none] [&>*]:flex-none">
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
                    </div>

                    @foreach($trips as $mapTrip)
                        <a href="{{ $mapTrip->filterUrl() }}" x-show="activeTrip === @js($mapTrip->slug)" x-cloak
                           class="mt-3 inline-block text-sm text-brand hover:underline underline-offset-4">
                            Lees alle delen op volgorde →
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Stories in the current map view --}}
        <aside class="min-w-0 lg:col-span-1 lg:relative"
               x-data="{
                   // Like the map: the wheel scrolls the list only once the mouse really moved over it (see wakeOnMove)
                   awake: ! window.matchMedia('(hover: hover)').matches,
                   moved: 0,
                   wake(event) {
                       this.moved += Math.abs(event.movementX) + Math.abs(event.movementY);
                       if (this.moved >= 5) this.awake = true;
                   },
                   sleep() {
                       this.moved = 0;
                       if (window.matchMedia('(hover: hover)').matches) this.awake = false;
                   },
                   moreAbove: false,
                   moreBelow: false,
                   checkScroll() {
                       const list = this.$refs.stories;
                       this.moreAbove = list.scrollTop > 4;
                       this.moreBelow = list.scrollTop + list.clientHeight < list.scrollHeight - 4;
                   },
                   gliding: null,
                   scrollStories(direction) {
                       if (this.gliding) return;
                       this.$refs.stories.scrollBy({ top: direction * this.$refs.stories.clientHeight * 0.8, behavior: 'smooth' });
                   },
                   // While the pointer rests on an arrow, the list keeps moving (by time, so as fast on every screen)
                   glide(direction) {
                       this.stopGliding();
                       let last = performance.now();
                       const step = (now) => {
                           this.$refs.stories.scrollTop += direction * (now - last) * 0.25;
                           last = now;
                           this.checkScroll();
                           // A hidden arrow gets no mouseleave, so stop at the end by itself
                           this.gliding = (direction < 0 ? this.moreAbove : this.moreBelow) ? requestAnimationFrame(step) : null;
                       };
                       this.gliding = requestAnimationFrame(step);
                   },
                   stopGliding() {
                       cancelAnimationFrame(this.gliding);
                       this.gliding = null;
                   },
               }"
               x-effect="visible.length; $nextTick(() => checkScroll())"
               x-on:resize.window.debounce.100ms="checkScroll()"
               x-on:mousemove="awake || wake($event)"
               x-on:mousedown="awake = true"
               x-on:mouseleave="sleep()">
            {{-- Taken out of the flow, so the column is as tall as the map and trips, not as long as the list --}}
            <div x-ref="stories" x-on:scroll.throttle.50ms="checkScroll()"
                 x-bind:class="awake ? 'lg:overflow-y-auto' : 'lg:overflow-y-hidden'"
                 class="lg:absolute lg:inset-0 lg:pe-2 [scrollbar-gutter:stable] [scrollbar-width:thin] [scrollbar-color:var(--color-night-500)_transparent]">
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
            </div>

            <button type="button" x-show="moreAbove" x-transition.opacity x-cloak x-on:click="scrollStories(-1)" x-on:mouseenter="glide(-1)" x-on:mouseleave="stopGliding()" aria-label="Eerdere verhalen in de lijst"
                    class="absolute left-1/2 top-2 z-10 hidden h-9 w-9 -translate-x-1/2 items-center justify-center rounded-full bg-night-900/90 text-cream shadow-soft ring-1 ring-white/10 backdrop-blur transition hover:bg-brand hover:text-night-900 lg:flex">
                <i class="fas fa-chevron-up"></i>
            </button>
            <button type="button" x-show="moreBelow" x-transition.opacity x-cloak x-on:click="scrollStories(1)" x-on:mouseenter="glide(1)" x-on:mouseleave="stopGliding()" aria-label="Meer verhalen in de lijst"
                    class="absolute bottom-2 left-1/2 z-10 hidden h-9 w-9 -translate-x-1/2 items-center justify-center rounded-full bg-night-900/90 text-cream shadow-soft ring-1 ring-white/10 backdrop-blur transition hover:bg-brand hover:text-night-900 lg:flex">
                <i class="fas fa-chevron-down"></i>
            </button>
        </aside>
    </div>
</x-app-layout>
