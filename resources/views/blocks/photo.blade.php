<figure @class(['not-prose my-10', 'lg:-mx-24' => $wide])
        x-data="{ images: @js([['src' => $full, 'alt' => $alt]]) }">
    <button type="button" x-on:click="$store.lightbox.show(images, 0)"
            class="group block w-full overflow-hidden rounded-2xl bg-night-900 shadow-soft cursor-zoom-in">
        <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy"
             class="w-full transition duration-700 group-hover:scale-[1.02]">
    </button>
    @if(filled($caption))
        <figcaption class="mt-3 text-center text-sm text-cream-muted">{{ $caption }}</figcaption>
    @endif
</figure>
