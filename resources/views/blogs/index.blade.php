@php
    // Show the newest blog large on the first page of the main overview
    $featured = (! isset($country) && $blogs->onFirstPage()) ? $blogs->first() : null;
    $gridBlogs = $featured ? $blogs->slice(1) : $blogs;
@endphp

<x-app-layout :title="isset($country) ? $country->name : null">
    <x-slot name="header">
        <p class="text-xs uppercase tracking-widest font-semibold text-accent">
            {{ isset($country) ? 'Reisblogs' : 'Welkom' }}
        </p>
        <h1 class="mt-2 font-display text-4xl sm:text-5xl text-cream">
            {{ isset($country) ? $country->name : 'Verhalen van onderweg' }}
        </h1>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        @if($featured)
            <article x-data x-intersect.once="$el.classList.add('is-visible')" class="reveal mb-14">
                <a href="{{ route('blogs.show', $featured->id) }}"
                   class="group grid lg:grid-cols-5 rounded-3xl overflow-hidden bg-night-700 shadow-soft ring-1 ring-white/5 hover:shadow-lift transition duration-300">
                    <div class="relative lg:col-span-3 aspect-[16/10] lg:aspect-auto lg:min-h-[26rem] overflow-hidden">
                        <img src="{{ $featured->coverUrl(1400, 900) }}" alt="{{ $featured->title }}"
                             class="absolute inset-0 w-full h-full object-cover transition duration-700 group-hover:scale-105" />
                        <span class="absolute top-4 left-4 pill bg-accent text-night-900">Nieuwste verhaal</span>
                    </div>
                    <div class="lg:col-span-2 flex flex-col justify-center p-8 lg:p-10">
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($featured->countries as $featuredCountry)
                                <span class="pill bg-night-600 text-cream">
                                    <i class="fas fa-map-marker-alt text-accent"></i>{{ $featuredCountry->name }}
                                </span>
                            @endforeach
                        </div>
                        <span class="mt-4 text-xs uppercase tracking-widest font-semibold text-accent">
                            {{ $featured->categories->pluck('name')->join(' · ') }}
                        </span>
                        <h2 class="mt-2 font-display text-3xl lg:text-4xl leading-tight text-cream group-hover:text-brand transition">{{ $featured->title }}</h2>
                        @if($featured->excerpt !== 'No excerpt')<p class="mt-4 text-lg text-cream-muted line-clamp-4">{{ $featured->excerpt }}</p>@endif
                        <p class="mt-6 text-sm text-cream-muted">
                            {{ $featured->placed_by->name }} · {{ $featured->created_at->translatedFormat('d F Y') }}
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

        @if($blogs->isEmpty())
            <p class="text-center text-cream-muted py-20">Hier zijn nog geen verhalen te lezen.</p>
        @else
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($gridBlogs as $blog)
                    @include('blogs.partials.card', ['blog' => $blog])
                @endforeach
            </div>
        @endif

        <div class="mt-14">
            {{ $blogs->links() }}
        </div>
    </div>
</x-app-layout>
