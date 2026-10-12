@php
    // $id: unique per search bar (desktop and mobile); $inputClass: its size
    $current = request()->routeIs('search') ? (string) request()->query('q', '') : '';
@endphp

{{-- Search bar: suggestions while typing; Enter opens all results (or the highlighted suggestion) --}}
<form action="{{ route('search') }}" method="GET" role="search"
      x-data="siteSearch({ suggestUrl: @js(route('search.suggest')), searchUrl: @js(route('search')) })"
      x-on:submit="submit($event)" x-on:click.outside="close()" x-on:keydown.escape="close(true)"
      class="relative {{ $formClass ?? '' }}">
    <div class="relative">
    <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
        <svg class="w-4 h-4 text-cream-muted" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/>
        </svg>
    </div>
    <input type="search" name="q" id="{{ $id }}" x-ref="input" value="{{ $current }}" maxlength="100" autocomplete="off"
           role="combobox" aria-label="Zoek in de verhalen" aria-autocomplete="list" aria-controls="{{ $id }}-list"
           x-bind:aria-expanded="open.toString()"
           x-bind:aria-activedescendant="active >= 0 ? '{{ $id }}-option-' + active : null"
           x-model="query" x-on:input.debounce.200ms="suggest()" x-on:focus="suggest()"
           x-on:keydown.arrow-down.prevent="move(1)" x-on:keydown.arrow-up.prevent="move(-1)"
           class="{{ $inputClass }} p-2 ps-10 text-sm rounded-full bg-night-700 border-night-600 text-cream placeholder-cream-muted focus:ring-brand focus:border-brand [&::-webkit-search-cancel-button]:hidden"
           placeholder="Zoeken...">
    </div>

    <div x-show="open" x-cloak x-transition.opacity.duration.150ms
         class="{{ $panelClass ?? '' }} z-50 mt-2 overflow-hidden rounded-2xl bg-night-700 shadow-lift ring-1 ring-white/10">
        <ul id="{{ $id }}-list" role="listbox" aria-label="Suggesties" class="max-h-[70vh] overflow-y-auto py-2">
            <template x-for="(option, index) in options" x-bind:key="option.kind + option.url">
                <li x-bind:id="'{{ $id }}-option-' + index" role="option" x-bind:aria-selected="(active === index).toString()"
                    x-on:mouseenter="active = index" x-on:mousedown.prevent x-on:click="go(option)"
                    x-bind:class="active === index ? 'bg-night-600' : ''"
                    class="cursor-pointer px-3">
                    {{-- A blog: photo, title with the typed words marked, place and month --}}
                    <template x-if="option.kind === 'blog'">
                        <div class="flex items-center gap-3 py-2">
                            <img x-bind:src="option.cover" alt="" class="h-12 w-12 flex-none rounded-lg object-cover bg-night-800">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-cream">
                                    <template x-for="part in parts(option.title)"><span x-bind:class="part.hit && 'text-brand'" x-text="part.text"></span></template>
                                </p>
                                <p class="truncate text-xs text-cream-muted" x-text="[option.place, option.date].filter(Boolean).join(' · ')"></p>
                            </div>
                        </div>
                    </template>
                    {{-- A country or trip --}}
                    <template x-if="option.kind === 'place'">
                        <div class="flex items-center gap-3 py-2 text-sm text-cream">
                            <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-night-800 text-accent">
                                <i class="fas" x-bind:class="option.type === 'trip' ? 'fa-route' : 'fa-map-marker-alt'"></i>
                            </span>
                            <span>
                                <span x-text="option.type === 'trip' ? 'Reis' : 'Land'" class="text-xs uppercase tracking-widest text-cream-muted"></span>
                                <span class="block font-medium" x-text="option.label"></span>
                            </span>
                        </div>
                    </template>
                    {{-- Everything: the same as pressing Enter --}}
                    <template x-if="option.kind === 'all'">
                        <div class="mt-1 flex items-center justify-between gap-3 border-t border-night-600 py-3 text-sm text-brand">
                            <span x-text="`Toon alle ${total} ${total === 1 ? 'verhaal' : 'verhalen'} voor “${query.trim()}”`"></span>
                            <kbd class="rounded bg-night-800 px-1.5 py-0.5 text-[0.7rem] text-cream-muted">Enter</kbd>
                        </div>
                    </template>
                </li>
            </template>
        </ul>

        <p x-show="loading && results.length === 0" class="px-4 pb-3 text-sm text-cream-muted">Zoeken…</p>
        <div x-show="! loading && total === 0 && query.trim().length >= 2" class="px-4 pb-4 text-sm text-cream-muted">
            <p>Geen verhalen gevonden.</p>
            <button type="button" x-show="didYouMean" x-on:click="query = didYouMean; suggest()"
                    class="mt-1 text-brand hover:underline underline-offset-4">
                Bedoelde je “<span x-text="didYouMean"></span>”?
            </button>
        </div>
        <p class="sr-only" role="status" aria-live="polite"
           x-text="loading ? '' : (total === 0 ? 'Geen verhalen gevonden' : `${total} ${total === 1 ? 'verhaal' : 'verhalen'} gevonden`)"></p>
    </div>
</form>
