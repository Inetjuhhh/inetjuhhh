<article x-data x-intersect.once="$el.classList.add('is-visible')" class="reveal h-full">
    <a href="{{ route('blogs.show', $blog) }}"
       class="group flex flex-col h-full rounded-2xl overflow-hidden bg-night-700 shadow-soft ring-1 ring-white/5
              hover:shadow-lift hover:-translate-y-1 transition duration-300">
        <div class="relative aspect-[4/3] overflow-hidden">
            <img src="{{ $blog->coverUrl(800, 600) }}" alt="{{ $blog->title }}" loading="lazy"
                 class="w-full h-full object-cover transition duration-700 group-hover:scale-105" />
            <div class="absolute inset-0 bg-gradient-to-t from-night-900/60 via-transparent to-transparent"></div>
            @if($blog->countries->isNotEmpty())
                <div class="absolute top-3 left-3 flex flex-wrap gap-1.5">
                    @foreach($blog->countries as $blogCountry)
                        <span class="pill bg-night-900/70 backdrop-blur text-cream">
                            <i class="fas fa-map-marker-alt text-accent"></i>{{ $blogCountry->name }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="flex flex-col flex-1 p-6">
            <span class="text-xs uppercase tracking-widest font-semibold text-accent">
                {{ $blog->categories->isNotEmpty() ? $blog->categories->pluck('name')->join(' · ') : 'Geen categorieën' }}
            </span>
            <h2 class="mt-2 font-display text-2xl leading-snug text-cream group-hover:text-brand transition">{{ $blog->title }}</h2>
            @if($blog->excerpt !== 'No excerpt')<p class="mt-3 text-cream-muted line-clamp-3">{{ $blog->excerpt }}</p>@endif
            <div class="mt-auto pt-5 flex items-center justify-between text-sm text-cream-muted">
                <span>{{ $blog->publishedDate()->translatedFormat('d F Y') }}</span>
                <span class="inline-flex items-center gap-1.5 font-medium text-brand">
                    Lees meer
                    <svg class="w-3.5 h-3.5 transition-transform group-hover:translate-x-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 5h12m0 0L9 1m4 4L9 9"/>
                    </svg>
                </span>
            </div>
        </div>
    </a>
</article>
