@php
    $navLink = 'block py-2 px-3 rounded-lg text-cream-muted hover:text-cream hover:bg-night-700 md:hover:bg-transparent md:p-0 transition';
    $navActive = 'block py-2 px-3 rounded-lg text-brand md:p-0';
@endphp

<header class="sticky top-0 z-40 bg-night-800/80 backdrop-blur-md border-b border-night-600/60">
    <nav>
        <div class="max-w-7xl flex flex-wrap items-center justify-between mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <a href="{{ route('blogs.index') }}" class="group flex items-center gap-3">
                <img src="{{ asset('storage/img/avatar.ico') }}" class="h-9 w-9 rounded-full ring-2 ring-brand/50 group-hover:ring-brand transition" alt="Ine avatar" />
                <span class="flex flex-col leading-tight">
                    <span class="font-display text-2xl font-semibold text-cream">Inetjuhhh</span>
                    <span class="hidden sm:block text-xs italic text-cream-muted">Life is short, so enjoy it!</span>
                </span>
            </a>

            <div class="flex md:order-2 gap-1">
                <div class="relative hidden md:block">
                    <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                        <svg class="w-4 h-4 text-cream-muted" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/>
                        </svg>
                        <span class="sr-only">Search icon</span>
                    </div>
                    <input type="text" id="search-navbar" class="block w-56 focus:w-72 transition-all duration-300 p-2 ps-10 text-sm rounded-full bg-night-700 border-night-600 text-cream placeholder-cream-muted focus:ring-brand focus:border-brand" placeholder="Zoeken...">
                </div>
                <button data-collapse-toggle="navbar-search" type="button" class="inline-flex items-center p-2 w-10 h-10 justify-center rounded-lg md:hidden text-cream-muted hover:bg-night-700 focus:outline-none focus:ring-2 focus:ring-night-600" aria-controls="navbar-search" aria-expanded="false">
                    <span class="sr-only">Open main menu</span>
                    <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 17 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 1h15M1 7h15M1 13h15"/>
                    </svg>
                </button>
            </div>

            <div class="items-center justify-between hidden w-full md:flex md:w-auto md:order-1" id="navbar-search">
                <div class="relative mt-3 md:hidden">
                    <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                        <svg class="w-4 h-4 text-cream-muted" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/>
                        </svg>
                    </div>
                    <input type="text" id="search-navbar-mobile" class="block w-full p-2 ps-10 text-sm rounded-full bg-night-700 border-night-600 text-cream placeholder-cream-muted focus:ring-brand focus:border-brand" placeholder="Zoeken...">
                </div>
                <ul class="flex md:items-center flex-col p-4 md:p-0 mt-4 font-medium rounded-xl bg-night-700 md:bg-transparent md:space-x-8 md:flex-row md:mt-0">
                    <li>
                        <a href="{{ route('blogs.index') }}" class="{{ request()->routeIs('blogs.index') ? $navActive : $navLink }}" @if(request()->routeIs('blogs.index')) aria-current="page" @endif>Home</a>
                    </li>
                    <li>
                        <button id="dropdownHoverButton" data-dropdown-toggle="dropdownHover" data-dropdown-trigger="hover" type="button"
                                class="{{ request()->routeIs('blogs.blogCountry') ? $navActive : $navLink }} inline-flex items-center w-full md:w-auto">
                            Reisblogs
                            <svg class="w-2.5 h-2.5 ms-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/>
                            </svg>
                        </button>

                        <div id="dropdownHover" class="z-50 hidden w-48 rounded-xl bg-night-700 border border-night-600 shadow-soft overflow-hidden">
                            <ul class="py-2 text-sm" aria-labelledby="dropdownHoverButton">
                                @foreach($countries as $navCountry)
                                    <li>
                                        <a href="{{ route('blogs.blogCountry', $navCountry->id) }}"
                                           class="flex items-center gap-2 px-4 py-2 text-cream-muted hover:bg-night-600 hover:text-cream transition">
                                            <i class="fas fa-map-marker-alt text-accent text-xs"></i>{{ $navCountry->name }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </li>
                    <li>
                        <a href="#" class="{{ $navLink }}">Familie</a>
                    </li>
                    <li>
                        <a href="#" class="{{ $navLink }}">Lifestyle</a>
                    </li>
                    <li>
                        <a href="#" class="{{ $navLink }}">Ik &amp; wij</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>
