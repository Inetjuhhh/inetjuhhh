<figure class="not-prose my-10">
    <div class="aspect-[16/10] overflow-hidden rounded-2xl bg-night-900 shadow-soft ring-1 ring-white/5">
        <iframe src="{{ $embed }}" title="Kaart van {{ $place }}" loading="lazy"
                referrerpolicy="no-referrer-when-downgrade" class="h-full w-full border-0"></iframe>
    </div>
    <figcaption class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm text-cream-muted">
        <span><i class="fas fa-map-marker-alt text-accent me-1"></i>{{ $caption ?: $place }}</span>
        <a href="{{ $link }}" target="_blank" rel="noopener" class="text-brand hover:underline underline-offset-4">Open in Google Maps</a>
    </figcaption>
</figure>
