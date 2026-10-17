{{-- The travel timeline at the top of the Reizen page (SiteCache::timeline): solid for the trips we made,
     dashed after "nu" for the ones still to come. Opens at the newest end; scroll left to look back. --}}
@php
    $past = $items->where('upcoming', false)->values();
    $coming = $items->where('upcoming', true)->values();
@endphp

<section @class(['mb-10', $class ?? '']) aria-labelledby="trip-timeline-heading"
         x-data="{
             start: false, end: true, progress: 1, size: 1,
             update() {
                 const row = this.$refs.row;
                 this.start = row.scrollLeft < 8;
                 this.end = row.scrollLeft + row.clientWidth > row.scrollWidth - 8;
                 this.size = row.clientWidth / row.scrollWidth;
                 this.progress = row.scrollLeft / row.scrollWidth;
             },
             back() { this.$refs.row.scrollBy({ left: -this.$refs.row.clientWidth * 0.8, behavior: 'smooth' }) },
             forward() { this.$refs.row.scrollBy({ left: this.$refs.row.clientWidth * 0.8, behavior: 'smooth' }) },
         }"
         x-init="$refs.row.scrollLeft = $refs.row.scrollWidth; update()" x-on:resize.window="update()">
    <h2 id="trip-timeline-heading" class="font-display text-2xl text-cream">Mijn reizen</h2>

    <div class="relative">
        <ol x-ref="row" x-on:scroll.passive="update()" class="no-scrollbar mt-4 -mx-4 px-4 sm:mx-0 sm:px-0 flex overflow-x-auto pb-1">
            @foreach($past as $item)
                <li class="relative flex-none w-36">
                    {{-- The line through this stop: from the middle onwards for the very first trip --}}
                    <span @class(['absolute top-[88px] h-1 bg-brand', 'left-1/2 right-0' => $loop->first, 'inset-x-0' => ! $loop->first]) aria-hidden="true"></span>
                    <a href="{{ $item['url'] }}" class="group relative flex flex-col items-center px-2 text-center">
                        <img src="{{ $item['cover'] }}" alt="" loading="lazy"
                             class="h-14 w-20 rounded-lg object-cover ring-1 ring-white/10 transition group-hover:ring-brand">
                        <span @class(['relative mt-4 h-7 w-7 rounded-full border-4 border-brand bg-night-800 transition group-hover:bg-brand', 'h-5 w-5 mt-5' => $item['city']])></span>
                        <span class="mt-2 font-display text-[15px] leading-tight text-cream group-hover:text-brand transition">{{ $item['name'] }}</span>
                        <span class="mt-0.5 text-xs text-cream-muted">{{ $item['city'] ? 'citytrip · ' : '' }}{{ $item['month'] }}</span>
                    </a>
                </li>
            @endforeach

            {{-- Nu: where the line stops being solid --}}
            <li class="relative flex-none w-16" aria-label="Nu">
                <span class="absolute top-[88px] left-0 right-1/2 h-1 bg-brand" aria-hidden="true"></span>
                @if($coming->isNotEmpty())
                    <span class="absolute top-[89px] left-1/2 right-0 border-t-[3px] border-dashed border-sand" aria-hidden="true"></span>
                @endif
                <span class="absolute left-1/2 top-[56px] -translate-x-1/2 rounded-full bg-night-600 px-2.5 py-0.5 text-xs font-semibold text-cream">nu</span>
            </li>

            @foreach($coming as $item)
                @php $days = (int) today()->diffInDays(\Illuminate\Support\Carbon::parse($item['starts_on'])); @endphp
                <li class="relative flex-none w-40">
                    <span @class(['absolute top-[89px] border-t-[3px] border-dashed border-sand', 'left-0 right-1/2' => $loop->last, 'inset-x-0' => ! $loop->last]) aria-hidden="true"></span>
                    <div class="group relative flex flex-col items-center px-2 text-center">
                        <span class="flex h-14 items-end"><span class="pill bg-sand text-night-900 text-xs">✈️ Binnenkort</span></span>
                        <span class="relative mt-4 h-7 w-7 rounded-full border-[3px] border-dashed border-sand bg-night-800"></span>
                        {{-- Once the trip has a story online (a "we're packing" one, say), the whole stop links to it --}}
                        @if($item['url'])
                            <a href="{{ $item['url'] }}" class="mt-2 font-display text-[15px] leading-tight text-cream hover:text-brand transition after:absolute after:inset-0">{{ $item['name'] }}</a>
                        @else
                            <span class="mt-2 font-display text-[15px] leading-tight text-cream">{{ $item['name'] }}</span>
                        @endif
                        <span class="mt-0.5 text-xs font-semibold text-sand">nog {{ $days }} {{ $days === 1 ? 'dag' : 'dagen' }}</span>
                    </div>
                </li>
            @endforeach
        </ol>

        {{-- Back in time: the edge fades where older trips are, and a faint < scrolls back --}}
        <div x-show="! start" x-transition.opacity class="pointer-events-none absolute inset-y-0 left-0 w-24 bg-gradient-to-r from-night-800 to-transparent"></div>
        <button type="button" x-show="! start" x-transition.opacity x-on:click="back()" aria-label="Eerdere reizen"
                class="trip-back absolute inset-y-0 left-0 flex w-14 items-center justify-start pl-1 text-3xl text-cream/40 transition-colors hover:text-cream focus-visible:text-cream focus-visible:outline-none">
            <i class="fas fa-chevron-left" aria-hidden="true"></i>
        </button>

        {{-- ...and once you went back, a faint > on the right brings you forward again, towards now --}}
        <div x-show="! end" x-transition.opacity class="pointer-events-none absolute inset-y-0 right-0 w-24 bg-gradient-to-l from-night-800 to-transparent"></div>
        <button type="button" x-show="! end" x-transition.opacity x-on:click="forward()" aria-label="Latere reizen"
                class="trip-forward absolute inset-y-0 right-0 flex w-14 items-center justify-end pr-1 text-3xl text-cream/40 transition-colors hover:text-cream focus-visible:text-cream focus-visible:outline-none">
            <i class="fas fa-chevron-right" aria-hidden="true"></i>
        </button>
    </div>

    {{-- How far back you are --}}
    <div class="mt-4 h-1 rounded-full bg-night-700 overflow-hidden" aria-hidden="true">
        <div class="h-full rounded-full bg-brand transition-[margin] duration-150" x-bind:style="`width: ${size * 100}%; margin-left: ${progress * 100}%`"></div>
    </div>
</section>
