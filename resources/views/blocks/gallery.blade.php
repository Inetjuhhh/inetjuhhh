@php
    $lightbox = $images->map(fn ($image) => ['src' => $image['full'], 'alt' => $image['alt']])->values();
    $count = $images->count();
    $visible = $images->take(9);
@endphp

<figure class="not-prose my-10" x-data="{ images: @js($lightbox) }">
    @if($layout === 'slideshow')
        <div x-data="{
                index: 0,
                timer: null,
                count: {{ $count }},
                go(i) { this.index = (i + this.count) % this.count },
                play() { this.stop(); this.timer = setInterval(() => this.go(this.index + 1), 5000) },
                stop() { clearInterval(this.timer) },
             }"
             x-init="play()"
             x-on:mouseenter="stop()" x-on:mouseleave="play()"
             x-on:touchstart.passive="stop(); $el.dataset.x = $event.touches[0].clientX"
             x-on:touchend.passive="const dx = $event.changedTouches[0].clientX - $el.dataset.x; if (Math.abs(dx) > 40) go(index + (dx < 0 ? 1 : -1))"
             class="group relative aspect-[3/2] overflow-hidden rounded-2xl bg-night-900 shadow-soft">
            @foreach($images as $i => $image)
                <img src="{{ $image['src'] }}" srcset="{{ $image['srcset'] }}" sizes="(min-width: 768px) 768px, 100vw" alt="{{ $image['alt'] }}" loading="lazy"
                     x-show="index === {{ $i }}" x-transition.opacity.duration.700ms
                     x-on:click="$store.lightbox.show(images, {{ $i }})"
                     class="absolute inset-0 h-full w-full cursor-zoom-in object-cover" @if($i > 0) style="display: none" @endif>
            @endforeach

            <button type="button" x-on:click="go(index - 1)" aria-label="Vorige foto"
                    class="absolute left-3 top-1/2 -translate-y-1/2 flex h-10 w-10 items-center justify-center rounded-full bg-night-900/70 text-cream backdrop-blur transition hover:bg-brand hover:text-night-900 sm:opacity-0 sm:group-hover:opacity-100">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button type="button" x-on:click="go(index + 1)" aria-label="Volgende foto"
                    class="absolute right-3 top-1/2 -translate-y-1/2 flex h-10 w-10 items-center justify-center rounded-full bg-night-900/70 text-cream backdrop-blur transition hover:bg-brand hover:text-night-900 sm:opacity-0 sm:group-hover:opacity-100">
                <i class="fas fa-chevron-right"></i>
            </button>

            <div class="absolute inset-x-0 bottom-3 flex justify-center gap-1.5">
                @foreach($images as $i => $image)
                    <button type="button" x-on:click="go({{ $i }})" aria-label="Foto {{ $i + 1 }}"
                            class="h-1.5 rounded-full transition-all"
                            x-bind:class="index === {{ $i }} ? 'w-6 bg-cream' : 'w-1.5 bg-cream/50'"></button>
                @endforeach
            </div>
        </div>
    @else
        @php
            // Mixed rows (see GalleryLayout): a landscape with two portraits on a laptop, with one on a phone.
            // Each photo keeps its own shape; the lightbox keeps the order they were chosen in.
            $photos = $visible->values()->map(fn ($image, $i) => $image + ['index' => $i])->all();
            $lastShown = count($photos) - 1;
        @endphp
        @foreach(['laptop' => [2, 'hidden sm:flex', 420, 768], 'phone' => [1, 'flex sm:hidden', 320, null]] as $screen => [$perRow, $show, $maxHeight, $width])
            <div class="{{ $show }} flex-col gap-2 sm:gap-3">
                @foreach(App\Support\GalleryLayout::rows($photos, $perRow) as $row)
                    @php $sum = App\Support\GalleryLayout::ratioSum($row); @endphp
                    {{-- A row of only one portrait would become huge at full width, so a row is at most $maxHeight high --}}
                    <div class="flex w-full gap-2 sm:gap-3 mx-auto" style="max-width: {{ round($sum * $maxHeight) }}px">
                        @foreach($row as $image)
                            @php $share = $image['ratio'] / $sum; @endphp
                            <button type="button" x-on:click="$store.lightbox.show(images, {{ $image['index'] }})"
                                    class="group relative min-w-0 overflow-hidden rounded-xl bg-night-900 cursor-zoom-in"
                                    style="flex: {{ $image['ratio'] }} 1 0%; aspect-ratio: {{ $image['ratio'] }}">
                                <img src="{{ $image['src'] }}" srcset="{{ $image['srcset'] }}" alt="{{ $image['alt'] }}" loading="lazy"
                                     sizes="{{ $width ? '(min-width: 768px) ' . round($share * $width) . 'px, ' : '' }}{{ round($share * 100) }}vw"
                                     class="h-full w-full object-cover transition duration-700 group-hover:scale-105">
                                @if($image['index'] === $lastShown && $count > $visible->count())
                                    <span class="absolute inset-0 flex items-center justify-center bg-night-900/70 font-display text-3xl text-cream">
                                        +{{ $count - $visible->count() }}
                                    </span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endforeach
    @endif

    @if(filled($caption))
        <figcaption class="mt-3 text-center text-sm text-cream-muted">{{ $caption }}</figcaption>
    @endif
</figure>
