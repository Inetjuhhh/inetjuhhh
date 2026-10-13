@if($related->previous || $related->next || $related->more->isNotEmpty())
    <section class="mt-20 border-t border-night-600 bg-night-900/40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
            <p class="text-xs font-semibold uppercase tracking-widest text-accent">Lees verder</p>
            <h2 class="mt-2 font-display text-3xl text-cream sm:text-4xl">Waar gaat de reis verder?</h2>

            {{-- Previous and next story, from the same region when there is one --}}
            @if($related->previous || $related->next)
                <div class="mt-8 grid gap-6 md:grid-cols-2">
                    @foreach([
                        ['blog' => $related->previous, 'label' => $related->previousLabel, 'next' => false],
                        ['blog' => $related->next, 'label' => $related->nextLabel, 'next' => true],
                    ] as $item)
                        @if($item['blog'])
                            <a href="{{ route('blogs.show', $item['blog']) }}"
                               @class([
                                   'group relative isolate flex min-h-56 overflow-hidden rounded-3xl shadow-soft ring-1 ring-white/5 hover:shadow-lift transition duration-300',
                                   'md:col-start-2' => $item['next'] && ! $related->previous,
                               ])>
                                <img src="{{ $item['blog']->coverUrl(900, 600) }}" alt="" loading="lazy"
                                     class="absolute inset-0 -z-10 h-full w-full object-cover transition duration-700 group-hover:scale-105">
                                <span class="absolute inset-0 -z-10 bg-gradient-to-t from-night-900 via-night-900/70 to-night-900/20"></span>

                                <span @class(['mt-auto flex w-full flex-col p-6 sm:p-8', 'items-end text-right' => $item['next']])>
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
            @endif

            {{-- A few more stories that are nearby or about the same things --}}
            @if($related->more->isNotEmpty())
                <h3 class="mt-14 font-display text-2xl text-cream">
                    {{ $related->moreInRegion ? 'Meer verhalen in de buurt' : 'Misschien wil je andere dingen van me lezen :)' }}
                </h3>
                <div class="mt-6 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($related->more as $blog)
                        @include('blogs.partials.card', ['blog' => $blog])
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endif
