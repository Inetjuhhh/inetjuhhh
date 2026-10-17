{{-- What the stories in a category are about, as bubbles: the bigger, the more stories. A list of links
     without JavaScript (and for screen readers); subject-bubbles.js draws the bubbles over it. --}}
<section x-data="subjectBubbles(@js($subjects))" class="{{ $class ?? 'mt-8' }} rounded-3xl bg-night-700 ring-1 ring-white/5 p-4 sm:p-7">
    <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2">
        <p class="text-cream-muted">
            @if($subject)
                Verhalen over <span class="font-semibold text-cream">{{ $subject['name'] }}</span>
            @elseif($category->slug === 'lifestyle')
                Buiten reisblogs schrijf ik nog over andere leuke (en minder leuke) dingen. Klik op een onderwerp om meer te lezen over...
            @else
                Waar gaan deze verhalen over? Klik op een onderwerp om alleen die verhalen te zien.
            @endif
        </p>
        @if($subject)
            <a href="{{ route('blogs.category', $category) }}" class="inline-flex items-center gap-2 text-sm text-brand hover:underline underline-offset-4">
                <i class="fas fa-times"></i> Alle onderwerpen
            </a>
        @endif
    </div>

    <div x-ref="chart" class="mt-4 empty:hidden" aria-hidden="true"></div>

    <ul x-ref="list" class="mt-4 flex flex-wrap gap-2" aria-label="Onderwerpen">
        @foreach($subjects as $item)
            <li>
                <a href="{{ $item['url'] }}" @class(['pill px-3.5 py-2 text-sm transition', 'bg-brand text-night-900' => $item['active'], 'bg-night-600 text-cream hover:bg-night-500' => ! $item['active']])
                   @if($item['active']) aria-current="true" @endif>
                    {{ $item['name'] }} <span class="opacity-70">{{ $item['count'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</section>
