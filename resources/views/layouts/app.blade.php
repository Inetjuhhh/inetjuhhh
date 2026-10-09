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
        <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha256-4+XzXVhsDmqanXGHaHvgh1gMQKX40OUvDEBTu8JcmNs=" crossorigin="anonymous"></script>
        <script src="{{ asset('js/share.js') }}"></script>
    </body>
</html>
