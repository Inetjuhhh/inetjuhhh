{{-- More stories at the bottom of a blog; the previous and next one are in related-neighbours --}}
@if($related->more->isNotEmpty())
    <section class="mt-20 border-t border-night-600 bg-night-900/40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">

            {{-- A few more stories that are nearby or about the same things --}}
            @if($related->more->isNotEmpty())
                <h2 class="font-display text-2xl text-cream sm:text-3xl">
                    {{ $related->moreInRegion ? 'Meer verhalen in de buurt' : 'Misschien wil je andere dingen van me lezen :)' }}
                </h2>
                <div class="mt-6 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($related->more as $blog)
                        @include('blogs.partials.card', ['blog' => $blog])
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endif
