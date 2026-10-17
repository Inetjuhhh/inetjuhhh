{{-- The trips as a row of photo cards at the top of the Reizen page (scroll or swipe sideways), with the way to the map --}}
<section class="mb-10" aria-labelledby="trip-cards-heading">
    <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-3">
        <h2 id="trip-cards-heading" class="font-display text-2xl text-cream">Mijn reizen</h2>
        <a href="{{ route('blogs.map') }}"
           class="inline-flex items-center gap-2 rounded-full bg-night-700 px-4 py-2 text-sm text-cream ring-1 ring-white/5 transition hover:bg-brand hover:text-night-900">
            <i class="fas fa-map-marked-alt text-accent"></i> Bekijk al mijn reizen op de kaart
        </a>
    </div>

    <ul class="mt-4 -mx-4 px-4 sm:mx-0 sm:px-0 flex gap-4 overflow-x-auto pb-3 snap-x snap-mandatory">
        @foreach($trips as $trip)
            <li class="snap-start flex-none">
                <a href="{{ $trip['url'] }}" class="group relative block w-56 h-36 overflow-hidden rounded-2xl bg-night-700 ring-1 ring-white/5">
                    <img src="{{ $trip['cover'] }}" alt="" loading="lazy"
                         class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-105">
                    <div class="absolute inset-0 bg-gradient-to-t from-night-900/90 via-night-900/20 to-transparent"></div>
                    <span class="absolute top-2 left-2 pill bg-night-900/70 backdrop-blur text-cream text-xs">
                        {{ $trip['city'] ? '🏙️ Citytrip' : '🧭 Reis' }}
                    </span>
                    <span class="absolute bottom-3 left-3 right-3 font-display text-lg leading-tight text-cream group-hover:text-brand transition">{{ $trip['name'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</section>
