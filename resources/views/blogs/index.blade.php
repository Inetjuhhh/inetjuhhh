@php
    $trip ??= null;
    $search ??= null;
    // Show the newest blog large on the first page of the main overview
    $featured = (! isset($country) && ! isset($category) && ! $trip && $search === null && $blogs->onFirstPage()) ? $blogs->first() : null;
    $gridBlogs = $featured ? $blogs->slice(1) : $blogs;
    // A category page with subjects: the bubbles sit next to the first story of the page, shown large
    // (on the first page that is the newest story)
    $bubbles = isset($category) && count($subjects ?? []) >= 2;
    $newest = $bubbles ? $blogs->first() : null;
    if ($newest) {
        $gridBlogs = $blogs->slice(1);
    }
@endphp

<x-app-layout :title="$search !== null ? 'Zoeken: ' . $search : ($trip?->name ?? (isset($country) ? $country->name : (isset($category) ? $category->name : null)))">
    <x-slot name="header">
        @if($search !== null)
            {{-- Search results, with the typo fix when that was needed --}}
            <p class="text-xs uppercase tracking-widest font-semibold text-accent">Zoeken</p>
            <h1 class="mt-2 font-display text-4xl sm:text-5xl text-cream">
                @if($search === '')
                    Waar ben je naar op zoek?
                @else
                    {{ $blogs->total() }} {{ $blogs->total() === 1 ? 'verhaal' : 'verhalen' }} over “{{ $searchedFor }}”
                @endif
            </h1>
            @if($searchedFor !== $search)
                <p class="mt-3 text-cream-muted">Niets gevonden voor “{{ $search }}”, dus ik heb gezocht naar “{{ $searchedFor }}”.</p>
            @endif
            @if($places->isNotEmpty())
                <div class="mt-6 flex flex-wrap items-center gap-2">
                    <span class="text-sm text-cream-muted">Bekijk ook:</span>
                    @foreach($places as $place)
                        <a href="{{ $place['url'] }}" class="pill bg-night-700 text-cream hover:bg-night-600 transition">
                            <i class="fas {{ $place['type'] === 'trip' ? 'fa-route' : 'fa-map-marker-alt' }} text-accent"></i>{{ $place['label'] }}
                        </a>
                    @endforeach
                </div>
            @endif
        @elseif($trip)
            {{-- One trip, all parts in reading order --}}
            <p class="text-xs uppercase tracking-widest font-semibold text-accent">🧭 Reis</p>
            <h1 class="mt-2 font-display text-4xl sm:text-5xl text-cream">{{ $trip->name }}</h1>
            <p class="mt-3 text-cream-muted">
                {{ collect([$trip->period(), $blogs->total() . ' ' . ($blogs->total() === 1 ? 'deel' : 'delen')])->filter()->join(' · ') }}
            </p>
            @if(filled($trip->description))
                <p class="mt-4 max-w-2xl text-lg leading-relaxed text-cream/90">{{ $trip->description }}</p>
            @endif
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ $trip->mapUrl() }}"
                   class="inline-flex items-center gap-2 rounded-full bg-brand px-5 py-2.5 font-semibold text-night-900 transition hover:bg-cream">
                    <i class="fas fa-route"></i> Bekijk de route op de kaart
                </a>
                <a href="{{ route('blogs.index') }}"
                   class="inline-flex items-center gap-2 rounded-full bg-night-700 px-5 py-2.5 text-cream transition hover:bg-night-600">
                    <i class="fas fa-times text-cream-muted"></i> Alle verhalen tonen
                </a>
            </div>
        @else
            <p class="text-xs uppercase tracking-widest font-semibold text-accent">
                {{ isset($country) ? 'Reisblogs' : (isset($category) ? 'Categorie' : 'Welkom') }}
            </p>
            <h1 class="mt-2 font-display text-4xl sm:text-5xl text-cream">
                {{ isset($country) ? $country->name : (isset($category) ? $category->name : 'Verhalen van onderweg') }}
            </h1>
            @if($bubbles && ! $newest)
                @include('blogs.partials.subject-bubbles', ['subjects' => $subjects, 'subject' => $subject ?? null, 'category' => $category])
            @endif
        @endif
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        @if($featured)
            <article x-data x-intersect.once="$el.classList.add('is-visible')" class="reveal mb-14">
                <a href="{{ route('blogs.show', $featured) }}"
                   class="group grid lg:grid-cols-5 rounded-3xl overflow-hidden bg-night-700 shadow-soft ring-1 ring-white/5 hover:shadow-lift transition duration-300">
                    <div class="relative lg:col-span-3 aspect-[16/10] lg:aspect-auto lg:min-h-[26rem] overflow-hidden">
                        <img src="{{ $featured->coverUrl(1400, 900) }}" srcset="{{ $featured->coverSrcset([800, 1200, 1600]) }}" sizes="(min-width: 1024px) 60vw, 100vw" alt="{{ $featured->title }}"
                             class="absolute inset-0 w-full h-full object-cover transition duration-700 group-hover:scale-105" />
                        <span class="absolute top-4 left-4 pill bg-accent text-night-900">Nieuwste verhaal</span>
                    </div>
                    <div class="lg:col-span-2 flex flex-col justify-center p-8 lg:p-10">
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($featured->placeTags() as $tag)
                                <span class="pill bg-night-600 text-cream">
                                    <i class="fas fa-map-marker-alt text-accent"></i>{{ $tag['label'] }}
                                </span>
                            @endforeach
                        </div>
                        <span class="mt-4 text-xs uppercase tracking-widest font-semibold text-accent">
                            {{ $featured->categories->pluck('name')->join(' · ') }}
                        </span>
                        <h2 class="mt-2 font-display text-3xl lg:text-4xl leading-tight text-cream group-hover:text-brand transition">{{ $featured->title }}</h2>
                        @if($featured->excerpt !== 'No excerpt')<p class="mt-4 text-lg text-cream-muted line-clamp-4">{{ $featured->excerpt }}</p>@endif
                        <p class="mt-6 text-sm text-cream-muted">
                            {{ $featured->placed_by->name }} · {{ $featured->publishedDate()->translatedFormat('d F Y') }}
                        </p>
                        <span class="mt-6 inline-flex items-center gap-2 self-start px-5 py-2.5 rounded-full bg-brand text-night-900 font-semibold group-hover:bg-cream transition">
                            Lees het verhaal
                            <svg class="w-3.5 h-3.5 transition-transform group-hover:translate-x-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 10">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 5h12m0 0L9 1m4 4L9 9"/>
                            </svg>
                        </span>
                    </div>
                </a>
            </article>
        @endif

        @if(! isset($country) && ! $trip && $search === null && filled($mapMarkers ?? null))
            @include('blogs.partials.map-teaser', ['markers' => $mapMarkers])
        @endif

        @if($blogs->isEmpty() && $search !== null)
            <div class="py-16 text-center">
                <p class="text-lg text-cream-muted">
                    {{ $search === '' ? 'Typ in de zoekbalk bovenaan waar je over wilt lezen.' : 'Ik heb (nog) niets geschreven dat hierbij past.' }}
                </p>
                <p class="mt-2 text-cream-muted">Probeer een land, een plaats of een woord als “kleuter” of “strand”, of</p>
                <a href="{{ route('blogs.map') }}" class="mt-6 inline-flex items-center gap-2 rounded-full bg-brand px-5 py-2.5 font-semibold text-night-900 transition hover:bg-cream">
                    <i class="fas fa-map-marked-alt"></i> zoek op de reiskaart
                </a>
            </div>
        @elseif($blogs->isEmpty())
            <p class="text-center text-cream-muted py-20">Hier zijn nog geen verhalen te lezen.</p>
        @else
            {{-- On the Reizen page (first page, all subjects): the trips as photo cards, and the way to the map --}}
            @if(isset($category) && $category->slug === 'reizen' && $blogs->onFirstPage() && ! ($subject ?? null) && ($tripCards ?? collect())->isNotEmpty())
                @include('blogs.partials.trip-cards', ['trips' => $tripCards])
            @endif
            @if($newest)
                {{-- Bubbles left, the newest story large beside them --}}
                <div class="grid lg:grid-cols-5 gap-8 mb-8">
                    @include('blogs.partials.subject-bubbles', ['subjects' => $subjects, 'subject' => $subject ?? null, 'category' => $category, 'class' => 'lg:col-span-2'])
                    <a href="{{ route('blogs.show', $newest) }}" class="lg:col-span-3 group relative flex min-h-[22rem] overflow-hidden rounded-3xl bg-night-700 shadow-soft ring-1 ring-white/5 hover:shadow-lift transition duration-300">
                        <img src="{{ $newest->coverUrl(1400, 900) }}" srcset="{{ $newest->coverSrcset([800, 1200, 1600]) }}" sizes="(min-width: 1024px) 60vw, 100vw" alt="{{ $newest->title }}"
                             class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-105">
                        <div class="absolute inset-0 bg-gradient-to-t from-night-900/90 via-night-900/30 to-transparent"></div>
                        {{-- Like every story card: where it is at the top, the trip above the title --}}
                        <div class="absolute top-4 left-4 right-4 flex flex-wrap gap-1.5">
                            @if($blogs->onFirstPage())
                                <span class="pill bg-accent text-night-900">Nieuwste verhaal</span>
                            @endif
                            @foreach($newest->placeTags() as $tag)
                                <span class="pill bg-night-900/70 backdrop-blur text-cream">
                                    <i class="fas fa-map-marker-alt text-accent"></i>{{ $tag['label'] }}
                                </span>
                            @endforeach
                        </div>
                        <div class="relative mt-auto p-6 lg:p-8">
                            @if($newest->trip)
                                <span class="pill bg-night-900/70 backdrop-blur text-cream">🧭 {{ $newest->trip->name }}</span>
                            @endif
                            <h2 class="mt-3 font-display text-3xl leading-tight text-cream group-hover:text-brand transition">{{ $newest->title }}</h2>
                            @if($newest->excerpt !== 'No excerpt')<p class="mt-3 text-cream/85 line-clamp-2">{{ $newest->excerpt }}</p>@endif
                            <p class="mt-4 text-sm text-cream-muted">{{ $newest->publishedDate()->translatedFormat('d F Y') }}</p>
                        </div>
                    </a>
                </div>
            @endif
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($gridBlogs as $blog)
                    @include('blogs.partials.card', [
                        'blog' => $blog,
                        // Within a trip, number the parts in reading order
                        'part' => $trip ? $blogs->firstItem() + $loop->index : null,
                    ])
                @endforeach
            </div>
        @endif

        <div class="mt-14">
            {{ $blogs->onEachSide(1)->links('pagination.route') }}
        </div>
    </div>
</x-app-layout>
