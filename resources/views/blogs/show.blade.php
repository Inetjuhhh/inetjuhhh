<x-app-layout :title="$blog->title" :description="$blog->shareDescription()" :image="$blog->shareImageUrl()"
              type="article" :published-at="$blog->publishedDate()->toIso8601String()" :noindex="$isPreview ?? false"
              :breadcrumbs="($blog->trip ? [$blog->trip->name => route('blogs.index', ['reis' => $blog->trip->slug])] : ($blog->categories->first() ? [$blog->categories->first()->name => route('blogs.category', $blog->categories->first())] : [])) + [$blog->title => route('blogs.show', $blog->slug)]">
    @if($isPreview ?? false)
        <div class="sticky top-[73px] z-30 bg-accent text-night-900 text-sm font-semibold text-center px-4 py-2">
            Voorbeeld — {{ $blog->isPublished() ? 'deze blog is gepubliceerd' : ($blog->status === 'published' ? 'ingepland voor ' . $blog->published_at->translatedFormat('d F Y H:i') : 'dit concept is nog niet zichtbaar voor bezoekers') }}
        </div>
    @endif
    {{-- Reading progress bar: how far into the story itself (full at its last line, not at the bottom of the comments) --}}
    <div x-data="{ progress: 0 }"
         x-on:scroll.window.passive="const box = document.getElementById('verhaal').getBoundingClientRect(); progress = Math.min(100, Math.max(0, -box.top / Math.max(1, box.height - window.innerHeight) * 100))"
         aria-hidden="true" class="fixed top-0 inset-x-0 z-50 h-1">
        <div class="h-full bg-gradient-to-r from-brand to-accent" :style="`width: ${progress}%`"></div>
    </div>

    {{-- Hero --}}
    <section class="relative h-[60vh] min-h-[22rem] max-h-[40rem] overflow-hidden">
        <img src="{{ $blog->coverUrl(1800, 1200) }}" srcset="{{ $blog->coverSrcset([800, 1200, 1800, 2400]) }}" sizes="100vw" alt="{{ $blog->title }}" class="absolute inset-0 w-full h-full object-cover" />
        <div class="absolute inset-0 bg-gradient-to-t from-night-800 via-night-800/50 to-night-900/20"></div>

        <div class="relative h-full max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col justify-end pb-10">
            <div class="flex flex-wrap gap-1.5">
                {{-- "India – Varanasi", linking to all stories from that country --}}
                @foreach($blog->placeTags() as $tag)
                    @if($tag['country'])
                        <a href="{{ route('blogs.blogCountry', $tag['country']->id) }}" class="pill bg-night-900/70 backdrop-blur text-cream hover:bg-night-900 transition">
                            <i class="fas fa-map-marker-alt text-accent"></i>{{ $tag['label'] }}
                        </a>
                    @else
                        <span class="pill bg-night-900/70 backdrop-blur text-cream">
                            <i class="fas fa-map-marker-alt text-accent"></i>{{ $tag['label'] }}
                        </span>
                    @endif
                @endforeach
            </div>
            @if($blog->categories->isNotEmpty())
                <span class="mt-4 text-xs uppercase tracking-widest font-semibold text-accent">
                    {{ $blog->categories->pluck('name')->join(' · ') }}
                </span>
            @endif
            <h1 class="mt-2 font-display text-4xl sm:text-5xl lg:text-6xl leading-tight text-cream drop-shadow-lg">{{ $blog->title }}</h1>
            <p class="mt-4 text-cream-muted">
                Door {{ $blog->placed_by->name }} · {{ $blog->publishedDate()->translatedFormat('d F Y') }}
            </p>
        </div>
    </section>

    {{-- Content --}}
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($blog->trip)
            @include('blogs.partials.trip-strip', ['trip' => $blog->trip, 'blog' => $blog])
        @endif

        @if($intro = $blog->introAboveStory())
            <p class="mt-10 text-xl leading-relaxed text-cream-muted border-l-2 border-accent pl-5">{{ $intro }}</p>
        @endif

        <article id="verhaal" class="mt-10 max-w-none prose prose-lg prose-night prose-headings:font-display prose-a:underline-offset-4 prose-img:rounded-xl">
            {!! $blog->renderContent() !!}
        </article>

        @php $interactive = $blog->isPublished(); @endphp

        <div class="mt-16">
            @include('blogs.partials.like-button', ['blog' => $blog, 'liked' => $liked ?? false, 'disabled' => ! $interactive])
        </div>

        @isset($related)
            @include('blogs.partials.related-neighbours', ['related' => $related])
        @endisset

        <div class="mt-16 pt-8 border-t border-night-600 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
            <div>
                <p class="text-xs uppercase tracking-widest font-semibold text-accent">Deel dit verhaal</p>
                <x-social-share :url="route('blogs.show', $blog)" :text="$blog->title" />
            </div>
            <a href="{{ route('blogs.index') }}" class="inline-flex items-center gap-2 self-start px-5 py-2.5 rounded-full bg-night-700 text-cream hover:bg-brand hover:text-night-900 transition">
                <svg class="w-3.5 h-3.5 rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 10">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 5h12m0 0L9 1m4 4L9 9"/>
                </svg>
                Alle verhalen
            </a>
        </div>

        @if($blog->trip && $tripMarkers?->isNotEmpty())
            @include('blogs.partials.trip-map', ['trip' => $blog->trip, 'markers' => $tripMarkers])
        @endif

        @include('blogs.partials.comments',['blog' => $blog, 'comments' => $comments ?? collect(), 'disabled' => ! $interactive])

        @include('blogs.partials.subscribe', ['variant' => 'blog'])
    </div>

    @isset($related)
        @include('blogs.partials.related', ['related' => $related])
    @endisset
</x-app-layout>
