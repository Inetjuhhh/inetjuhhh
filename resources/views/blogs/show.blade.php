<x-app-layout :title="$blog->title">
    @if($isPreview ?? false)
        <div class="sticky top-[73px] z-30 bg-accent text-night-900 text-sm font-semibold text-center px-4 py-2">
            Voorbeeld — {{ $blog->isPublished() ? 'deze blog is gepubliceerd' : ($blog->status === 'published' ? 'ingepland voor ' . $blog->published_at->translatedFormat('d F Y H:i') : 'dit concept is nog niet zichtbaar voor bezoekers') }}
        </div>
    @endif
    {{-- Reading progress bar --}}
    <div x-data="{ progress: 0 }"
         x-on:scroll.window="progress = Math.min(100, window.scrollY / (document.documentElement.scrollHeight - window.innerHeight) * 100)"
         class="fixed top-0 inset-x-0 z-50 h-1">
        <div class="h-full bg-gradient-to-r from-brand to-accent" :style="`width: ${progress}%`"></div>
    </div>

    {{-- Hero --}}
    <section class="relative h-[60vh] min-h-[22rem] max-h-[40rem] overflow-hidden">
        <img src="{{ $blog->coverUrl(1800, 1200) }}" alt="{{ $blog->title }}" class="absolute inset-0 w-full h-full object-cover" />
        <div class="absolute inset-0 bg-gradient-to-t from-night-800 via-night-800/50 to-night-900/20"></div>

        <div class="relative h-full max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col justify-end pb-10">
            <div class="flex flex-wrap gap-1.5">
                @foreach($blog->countries as $blogCountry)
                    <a href="{{ route('blogs.blogCountry', $blogCountry->id) }}" class="pill bg-night-900/70 backdrop-blur text-cream hover:bg-night-900 transition">
                        <i class="fas fa-map-marker-alt text-accent"></i>{{ $blogCountry->name }}
                    </a>
                @endforeach
            </div>
            <span class="mt-4 text-xs uppercase tracking-widest font-semibold text-accent">
                {{ $blog->categories->isNotEmpty() ? $blog->categories->pluck('name')->join(' · ') : 'Geen categorieën' }}
            </span>
            <h1 class="mt-2 font-display text-4xl sm:text-5xl lg:text-6xl leading-tight text-cream drop-shadow-lg">{{ $blog->title }}</h1>
            <p class="mt-4 text-cream-muted">
                Door {{ $blog->placed_by->name }} · {{ $blog->publishedDate()->translatedFormat('d F Y') }}
            </p>
        </div>
    </section>

    {{-- Content --}}
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($blog->excerpt && $blog->excerpt !== 'No excerpt')
            <p class="mt-10 text-xl leading-relaxed text-cream-muted border-l-2 border-accent pl-5">{{ $blog->excerpt }}</p>
        @endif

        <article class="mt-10 max-w-none prose prose-lg prose-night prose-headings:font-display prose-a:underline-offset-4 prose-img:rounded-xl">
            {!! $blog->renderContent() !!}
        </article>

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
    </div>
</x-app-layout>
