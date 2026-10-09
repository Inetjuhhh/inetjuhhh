<figure class="not-prose my-10">
    <div class="aspect-video overflow-hidden rounded-2xl bg-night-900 shadow-soft">
        <iframe src="{{ $embed }}" title="{{ $caption ?: 'Video' }}" loading="lazy"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                allowfullscreen class="h-full w-full"></iframe>
    </div>
    @if(filled($caption))
        <figcaption class="mt-3 text-center text-sm text-cream-muted">{{ $caption }}</figcaption>
    @endif
</figure>
