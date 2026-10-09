<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? $title . ' – ' : '' }}{{ config('app.name', 'Inetjuhhh') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|fraunces:500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-night-800 text-cream">
        <div class="min-h-screen flex flex-col">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="max-w-7xl w-full mx-auto pt-10 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </header>
            @endisset

            <!-- Page Content -->
            <main class="flex-1">
                {{ $slot }}
            </main>

            <footer class="mt-24 border-t border-night-600 bg-night-900">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 grid gap-10 md:grid-cols-3">
                    <div class="flex items-start gap-4 md:col-span-2">
                        <img src="{{ asset('storage/img/avatar.ico') }}" class="w-14 h-14 rounded-full ring-2 ring-brand/60" alt="Ine" />
                        <div>
                            <p class="font-display text-xl text-cream">Inetjuhhh</p>
                            <p class="mt-1 text-cream-muted max-w-md">Reisverhalen, familie en lifestyle. Life is short, so enjoy it!</p>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-widest text-accent font-semibold">Reisblogs</p>
                        <ul class="mt-3 flex flex-wrap gap-2">
                            @foreach($countries as $footerCountry)
                                <li>
                                    <a href="{{ route('blogs.blogCountry', $footerCountry->id) }}" class="pill bg-night-700 text-cream-muted hover:bg-night-600 hover:text-cream transition">{{ $footerCountry->name }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <div class="border-t border-night-700 py-5 text-center text-xs text-cream-muted">
                    &copy; {{ date('Y') }} Inetjuhhh
                </div>
            </footer>
        </div>

        {{-- Lightbox for photos in blog posts --}}
        <div x-data x-show="$store.lightbox.isOpen" x-transition.opacity style="display: none"
             x-on:keydown.escape.window="$store.lightbox.close()"
             x-on:keydown.arrow-right.window="$store.lightbox.isOpen && $store.lightbox.next()"
             x-on:keydown.arrow-left.window="$store.lightbox.isOpen && $store.lightbox.prev()"
             x-on:touchstart.passive="$el.dataset.x = $event.touches[0].clientX"
             x-on:touchend.passive="const dx = $event.changedTouches[0].clientX - $el.dataset.x; if (Math.abs(dx) > 40) dx < 0 ? $store.lightbox.next() : $store.lightbox.prev()"
             x-on:click.self="$store.lightbox.close()"
             class="fixed inset-0 z-[60] flex items-center justify-center bg-night-900/95 backdrop-blur-sm p-4 sm:p-10"
             role="dialog" aria-modal="true" aria-label="Foto bekijken">
            <img x-bind:src="$store.lightbox.current?.src" x-bind:alt="$store.lightbox.current?.alt"
                 class="max-h-full max-w-full rounded-lg object-contain shadow-lift">

            <button type="button" x-on:click="$store.lightbox.close()" aria-label="Sluiten"
                    class="absolute right-4 top-4 flex h-11 w-11 items-center justify-center rounded-full bg-night-700 text-cream hover:bg-brand hover:text-night-900 transition">
                <i class="fas fa-times"></i>
            </button>
            <template x-if="$store.lightbox.images.length > 1">
                <div>
                    <button type="button" x-on:click="$store.lightbox.prev()" aria-label="Vorige foto"
                            class="absolute left-4 top-1/2 -translate-y-1/2 hidden sm:flex h-12 w-12 items-center justify-center rounded-full bg-night-700 text-cream hover:bg-brand hover:text-night-900 transition">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" x-on:click="$store.lightbox.next()" aria-label="Volgende foto"
                            class="absolute right-4 top-1/2 -translate-y-1/2 hidden sm:flex h-12 w-12 items-center justify-center rounded-full bg-night-700 text-cream hover:bg-brand hover:text-night-900 transition">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <p class="absolute bottom-5 left-1/2 -translate-x-1/2 text-sm text-cream-muted"
                       x-text="`${$store.lightbox.index + 1} / ${$store.lightbox.images.length}`"></p>
                </div>
            </template>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha256-4+XzXVhsDmqanXGHaHvgh1gMQKX40OUvDEBTu8JcmNs=" crossorigin="anonymous"></script>
        <script src="{{ asset('js/share.js') }}"></script>
    </body>
</html>
