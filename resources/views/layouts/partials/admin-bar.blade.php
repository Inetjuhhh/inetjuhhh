@php
    // A preview binds the blog itself; a published story's route only has its slug
    $blog = request()->route('blog');
    $blog = $blog instanceof \App\Models\Blog
        ? $blog
        : (request()->routeIs('blogs.show') ? \App\Models\Blog::where('slug', request()->route('slug'))->first() : null);
@endphp

<div class="bg-night-900 border-b border-night-600/60 text-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex flex-wrap items-center gap-x-5 gap-y-1">
        <a href="{{ \Filament\Facades\Filament::getPanel('admin')->getUrl() }}" class="inline-flex items-center gap-2 text-cream hover:text-brand transition">
            <i class="fas fa-arrow-left"></i> Naar de admin
        </a>

        @if($blog)
            <a href="{{ \App\Filament\Resources\BlogResource::getUrl('edit', ['record' => $blog], panel: 'admin') }}" class="inline-flex items-center gap-2 text-cream hover:text-brand transition">
                <i class="fas fa-pen"></i> Bewerk dit verhaal
            </a>

            @unless($blog->isPublished())
                <span class="pill bg-accent/15 text-accent px-2.5 py-0.5 text-xs">
                    Voorbeeld: {{ $blog->status === 'published' ? 'ingepland, nog niet online' : 'nog niet online' }}
                </span>
            @endunless
        @endif

        <span class="ms-auto hidden sm:inline text-cream-muted">Alleen jij ziet deze balk</span>
    </div>
</div>
