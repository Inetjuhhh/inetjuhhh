{{-- The trips as a row of photo cards at the top of the Reizen page. No scrollbar: the edges fade where
     there is more, a thin line shows how far along you are, and a faint > moves the row on. --}}
<section class="mb-10" aria-labelledby="trip-cards-heading"
         x-data="{
             start: true, end: false, progress: 0, size: 1,
             update() {
                 const row = this.$refs.row;
                 this.start = row.scrollLeft < 8;
                 this.end = row.scrollLeft + row.clientWidth > row.scrollWidth - 8;
                 this.size = row.clientWidth / row.scrollWidth;
                 this.progress = row.scrollLeft / row.scrollWidth;
             },
             next() { this.$refs.row.scrollBy({ left: this.$refs.row.clientWidth * 0.8, behavior: 'smooth' }) },
         }"
         x-init="update()" x-on:resize.window="update()">
    <h2 id="trip-cards-heading" class="font-display text-2xl text-cream">Mijn reizen</h2>

    <div class="relative">
        <ul x-ref="row" x-on:scroll.passive="update()"
            class="no-scrollbar mt-4 -mx-4 px-4 sm:mx-0 sm:px-0 flex gap-4 overflow-x-auto snap-x snap-mandatory">
            @foreach($trips as $trip)
                <li class="snap-start flex-none">@include('blogs.partials.trip-card', ['trip' => $trip])</li>
            @endforeach
        </ul>

        {{-- The edges fade out where there is more to see --}}
        <div x-show="! start" x-transition.opacity class="pointer-events-none absolute inset-y-0 left-0 w-16 bg-gradient-to-r from-night-800 to-transparent"></div>
        <div x-show="! end" x-transition.opacity class="pointer-events-none absolute inset-y-0 right-0 w-24 bg-gradient-to-l from-night-800 to-transparent"></div>

        <button type="button" x-show="! end" x-transition.opacity x-on:click="next()" aria-label="Meer reizen"
                class="trip-next group absolute inset-y-0 right-0 flex w-14 items-center justify-end pr-1 text-3xl text-cream/40 transition-colors hover:text-cream focus-visible:text-cream focus-visible:outline-none">
            <i class="fas fa-chevron-right" aria-hidden="true"></i>
        </button>
    </div>

    {{-- How far along you are --}}
    <div class="mt-4 h-1 rounded-full bg-night-700 overflow-hidden" aria-hidden="true">
        <div class="h-full rounded-full bg-brand transition-[margin] duration-150" x-bind:style="`width: ${size * 100}%; margin-left: ${progress * 100}%`"></div>
    </div>
</section>
