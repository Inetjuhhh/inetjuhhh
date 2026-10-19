{{-- Counting down to the trips still to come (SiteCache::comingTrips). The first one big, with the dashed
     line from the day it was added to the departure filling up day by day; the ones after it underneath,
     each with its own count. A trip goes away once its first story is online. --}}
@php
    $trip = $trips[0];
    $later = array_slice($trips, 1);
    $starts = \Illuminate\Support\Carbon::parse($trip['starts_on']);
    $since = \Illuminate\Support\Carbon::parse($trip['counting_since']);
    $days = (int) today()->diffInDays($starts);
    $total = max(1, (int) $since->diffInDays($starts));
    $done = min(100, max(4, round((1 - $days / $total) * 100)));
    $daysLabel = fn (int $count) => $count === 1 ? 'dag' : 'dagen';
@endphp

<section @class(['rounded-3xl bg-night-700 ring-1 ring-white/5 p-6 sm:p-8', $class ?? ''])
         aria-label="Binnenkort: {{ collect($trips)->pluck('name')->join(', ') }}">
    <div class="grid gap-6 lg:grid-cols-2 lg:gap-10 items-center">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-accent">✈️ Binnenkort</p>
            <h2 class="mt-2 font-display text-3xl sm:text-4xl leading-tight text-cream">{{ $trip['name'] }}</h2>
            @if(filled($trip['description']))
                <p class="mt-3 max-w-xl text-cream-muted">{{ $trip['description'] }}</p>
            @endif
            <button type="button" x-data x-on:click="$dispatch('open-newsletter')"
                    class="mt-5 inline-flex items-center gap-2 rounded-full bg-brand px-5 py-2.5 font-semibold text-night-900 transition hover:bg-cream">
                <i class="fas fa-envelope"></i> Mail me als het eerste verhaal online staat
            </button>
        </div>

        <div>
            <p class="font-display text-6xl leading-none text-sand">
                {{ $days }} <span class="text-2xl text-cream-muted">{{ $daysLabel($days) }}</span>
            </p>
            <p class="mt-2 text-cream-muted">tot we vertrekken, op {{ $starts->translatedFormat('j F Y') }}</p>

            {{-- From the day it was planned to the departure: solid what has passed, dashed what is still to come --}}
            <div class="relative mt-5 h-14" aria-hidden="true" style="--done: {{ $done / 100 }}">
                <div class="absolute left-0 right-7 top-[26px] border-t-[3px] border-dashed border-sand/80"></div>
                <div class="absolute left-0 top-[25px] h-[5px] rounded-full bg-brand" style="width: calc((100% - 1.75rem) * var(--done))"></div>
                <div class="absolute top-[17px] -ml-3 h-6 w-6 rounded-full bg-brand ring-[5px] ring-brand/25" style="left: calc((100% - 1.75rem) * var(--done))"></div>
                <span class="absolute top-11 -translate-x-1/2 text-xs text-cream-muted" style="left: calc((100% - 1.75rem) * var(--done))">vandaag</span>
                <i class="fas fa-plane absolute right-0 top-[14px] text-2xl text-sand"></i>
                <span class="absolute right-0 top-11 text-xs text-cream-muted">vertrek</span>
            </div>
        </div>
    </div>

    {{-- The trips after the first one: each with its own count --}}
    @if($later)
        <div class="mt-7 border-t border-night-600 pt-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-cream-muted">Daarna</p>
            <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach($later as $next)
                    @php
                        $nextStarts = \Illuminate\Support\Carbon::parse($next['starts_on']);
                        $nextDays = (int) today()->diffInDays($nextStarts);
                    @endphp
                    <li class="flex items-center justify-between gap-4 rounded-2xl bg-night-800/70 px-4 py-3 ring-1 ring-white/5">
                        <span class="min-w-0">
                            <span class="block truncate font-display text-xl text-cream">{{ $next['name'] }}</span>
                            <span class="block text-xs text-cream-muted">vertrek {{ $nextStarts->translatedFormat('j F Y') }}</span>
                        </span>
                        <span class="flex-none text-right font-display text-sand">
                            <span class="text-2xl leading-none">{{ $nextDays }}</span>
                            <span class="text-sm text-cream-muted">{{ $daysLabel($nextDays) }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
