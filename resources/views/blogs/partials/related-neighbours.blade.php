{{-- Previous and next story, from the same region when there is one. Right under the story,
     so a reader following a trip doesn't have to scroll past the comments first. --}}
@if($related->previous || $related->next)
    <section class="mt-14" aria-labelledby="lees-verder">
        <p class="text-xs font-semibold uppercase tracking-widest text-accent">Lees verder</p>
        <h2 id="lees-verder" class="mt-2 font-display text-3xl text-cream">Waar gaat de reis verder?</h2>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            @foreach([
                ['blog' => $related->previous, 'label' => $related->previousLabel, 'next' => false],
                ['blog' => $related->next, 'label' => $related->nextLabel, 'next' => true],
            ] as $item)
                @if($item['blog'])
                    <a href="{{ route('blogs.show', $item['blog']) }}"
                       @class([
                           'group relative isolate flex min-h-48 overflow-hidden rounded-3xl shadow-soft ring-1 ring-white/5 hover:shadow-lift transition duration-300',
                           // The next part first on a phone: that's where most readers go
                           'order-first sm:order-none' => $item['next'],
                           'sm:col-start-2' => $item['next'] && ! $related->previous,
                       ])>
                        <img src="{{ $item['blog']->coverUrl(900, 600) }}" srcset="{{ $item['blog']->coverSrcset([400, 900]) }}" sizes="(min-width: 640px) 380px, 100vw" alt="" loading="lazy"
                             class="absolute inset-0 -z-10 h-full w-full object-cover transition duration-700 group-hover:scale-105">
                        <span class="absolute inset-0 -z-10 bg-gradient-to-t from-night-900 via-night-900/70 to-night-900/20"></span>

                        <span @class(['mt-auto flex w-full flex-col p-6', 'items-end text-right' => $item['next']])>
                            <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-accent">
                                @unless($item['next'])<i class="fas fa-arrow-left transition-transform group-hover:-translate-x-1"></i>@endunless
                                {{ $item['label'] }}
                                @if($item['next'])<i class="fas fa-arrow-right transition-transform group-hover:translate-x-1"></i>@endif
                            </span>
                            <span class="mt-2 font-display text-2xl leading-snug text-cream group-hover:text-brand transition">{{ $item['blog']->title }}</span>
                            <span class="mt-2 text-sm text-cream-muted">
                                {{ collect($item['blog']->placeTags())->pluck('label')->join(' · ') }}
                                · {{ $item['blog']->publishedDate()->translatedFormat('d F Y') }}
                            </span>
                        </span>
                    </a>
                @endif
            @endforeach
        </div>
    </section>
@endif
