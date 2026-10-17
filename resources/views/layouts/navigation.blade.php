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
                @include('layouts.partials.search', [
                    'id' => 'search-navbar',
                    'formClass' => 'hidden md:block',
                    'inputClass' => 'block w-56 focus:w-72 transition-all duration-300',
                    'panelClass' => 'absolute end-0 w-[26rem] max-w-[calc(100vw-2rem)]',
                ])
                <button data-collapse-toggle="navbar-search" type="button" class="inline-flex items-center p-2 w-10 h-10 justify-center rounded-lg md:hidden text-cream-muted hover:bg-night-700 focus:outline-none focus:ring-2 focus:ring-night-600" aria-controls="navbar-search" aria-expanded="false">
                    <span class="sr-only">Open main menu</span>
                    <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 17 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 1h15M1 7h15M1 13h15"/>
                    </svg>
                </button>
            </div>

            <div class="items-center justify-between hidden w-full md:flex md:w-auto md:order-1" id="navbar-search">
                @include('layouts.partials.search', [
                    'id' => 'search-navbar-mobile',
                    'formClass' => 'mt-3 md:hidden',
                    'inputClass' => 'block w-full',
                    'panelClass' => 'relative',
                ])
                <ul class="flex md:items-center flex-col p-4 md:p-0 mt-4 font-medium rounded-xl bg-night-700 md:bg-transparent md:space-x-8 md:flex-row md:mt-0">
                    <li>
                        <a href="{{ route('blogs.index') }}" class="{{ request()->routeIs('blogs.index') ? $navActive : $navLink }}" @if(request()->routeIs('blogs.index')) aria-current="page" @endif>Home</a>
                    </li>
                    {{-- TEMPORARY (choosing a menu): ?nav=nu|a|b|c, locally only --}}
                    @php $navVariant = app()->isLocal() ? request('nav', 'nu') : 'nu'; @endphp
                    @if($navVariant === 'nu')
                    <li>
                        <a href="{{ route('blogs.map') }}" class="{{ request()->routeIs('blogs.map') ? $navActive : $navLink }}" @if(request()->routeIs('blogs.map')) aria-current="page" @endif>
                            <i class="fas fa-map-marked-alt me-1 text-accent"></i>Kaart
                        </a>
                    </li>
                    @if($navTrips->isNotEmpty())
                        @include('layouts.partials.trip-menu', ['id' => 'dropdownTrips', 'label' => 'Reizen', 'icon' => '🧭', 'trips' => $navTrips])
                    @endif
                    @if($navCityTrips->isNotEmpty())
                        @include('layouts.partials.trip-menu', ['id' => 'dropdownCityTrips', 'label' => 'Citytrips', 'icon' => '🏙️', 'trips' => $navCityTrips])
                    @endif
                    <li>
                        <button id="dropdownCountriesButton" data-dropdown-toggle="dropdownCountries" data-dropdown-trigger="hover" type="button"
                                class="{{ request()->routeIs('blogs.blogCountry') ? $navActive : $navLink }} inline-flex items-center w-full md:w-auto">
                            Landen
                            <svg class="w-2.5 h-2.5 ms-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/>
                            </svg>
                        </button>

                        <div id="dropdownCountries" class="z-50 hidden w-80 rounded-xl bg-night-700 border border-night-600 shadow-soft overflow-hidden">
                            <ul class="grid grid-cols-2 py-2 text-sm" aria-labelledby="dropdownCountriesButton">
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
                    @else
                        @php
                            $travelUrl = route('blogs.category', 'travel');
                            $onTravel = request()->routeIs('blogs.category') && request()->route('category')?->slug === 'travel';
                            $chevron = '<svg class="w-2.5 h-2.5 ms-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/></svg>';
                        @endphp
                        @if($navVariant === 'a')
                            {{-- A: one Reizen menu (trips and city trips side by side), Kaart and Landen stay --}}
                            <li>
                                <a href="{{ route('blogs.map') }}" class="{{ $navLink }}"><i class="fas fa-map-marked-alt me-1 text-accent"></i>Kaart</a>
                            </li>
                            <li>
                                <button id="dropdownReizenButton" data-dropdown-toggle="dropdownReizen" data-dropdown-trigger="hover" type="button" class="{{ $onTravel ? $navActive : $navLink }} inline-flex items-center w-full md:w-auto">Reizen {!! $chevron !!}</button>
                                <div id="dropdownReizen" class="z-50 hidden w-[34rem] rounded-xl bg-night-700 border border-night-600 shadow-soft overflow-hidden">
                                    <div class="grid grid-cols-2 gap-2 p-3 text-sm">
                                        <div>
                                            <p class="px-3 pt-1 pb-2 text-xs uppercase tracking-widest font-semibold text-accent">🧭 Reizen</p>
                                            <ul class="max-h-80 overflow-y-auto">
                                                @foreach($navTrips as $menuTrip)
                                                    <li><a href="{{ $menuTrip->filterUrl() }}" class="block rounded-lg px-3 py-1.5 text-cream-muted hover:bg-night-600 hover:text-cream transition">{{ $menuTrip->name }}</a></li>
                                                @endforeach
                                            </ul>
                                        </div>
                                        <div>
                                            <p class="px-3 pt-1 pb-2 text-xs uppercase tracking-widest font-semibold text-accent">🏙️ Citytrips</p>
                                            <ul>
                                                @foreach($navCityTrips as $menuTrip)
                                                    <li><a href="{{ $menuTrip->filterUrl() }}" class="block rounded-lg px-3 py-1.5 text-cream-muted hover:bg-night-600 hover:text-cream transition">{{ $menuTrip->name }}</a></li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                    <a href="{{ $travelUrl }}" class="flex items-center justify-between border-t border-night-600 px-6 py-3 text-sm font-semibold text-brand hover:bg-night-600 transition">
                                        Alle reisverhalen, per onderwerp <span aria-hidden="true">→</span>
                                    </a>
                                </div>
                            </li>
                            <li>
                                <button id="dropdownCountriesButton" data-dropdown-toggle="dropdownCountries" data-dropdown-trigger="hover" type="button" class="{{ $navLink }} inline-flex items-center w-full md:w-auto">Landen {!! $chevron !!}</button>
                                <div id="dropdownCountries" class="z-50 hidden w-80 rounded-xl bg-night-700 border border-night-600 shadow-soft overflow-hidden">
                                    <ul class="grid grid-cols-2 py-2 text-sm">
                                        @foreach($countries as $navCountry)
                                            <li><a href="{{ route('blogs.blogCountry', $navCountry->id) }}" class="flex items-center gap-2 px-4 py-2 text-cream-muted hover:bg-night-600 hover:text-cream transition"><i class="fas fa-map-marker-alt text-accent text-xs"></i>{{ $navCountry->name }}</a></li>
                                        @endforeach
                                    </ul>
                                </div>
                            </li>
                        @elseif($navVariant === 'b')
                            {{-- B: one Reizen item with a wide panel: subjects, trips, city trips, countries; Kaart stays --}}
                            @php
                                $travelIds = App\Models\Blog::published()->whereHas('categories', fn ($q) => $q->where('slug', 'travel'))->select('blogs.id');
                                $travelSubjects = App\Models\Tag::query()->join('blog_tag', 'blog_tag.tag_id', '=', 'tags.id')->whereIn('blog_tag.blog_id', $travelIds)
                                    ->groupBy('tags.id', 'tags.name')->orderByRaw('count(*) desc')->limit(8)->pluck('tags.name');
                            @endphp
                            <li>
                                <button id="dropdownReizenButton" data-dropdown-toggle="dropdownReizen" data-dropdown-trigger="hover" type="button" class="{{ $onTravel ? $navActive : $navLink }} inline-flex items-center w-full md:w-auto">Reizen {!! $chevron !!}</button>
                                <div id="dropdownReizen" class="z-50 hidden w-[60rem] max-w-[calc(100vw-2rem)] rounded-2xl bg-night-700 border border-night-600 shadow-soft overflow-hidden">
                                    <div class="grid grid-cols-[1.2fr_1fr_0.8fr_1.3fr] gap-6 p-6 text-sm">
                                        <div>
                                            <p class="pb-3 text-xs uppercase tracking-widest font-semibold text-accent">Waar gaat het over?</p>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($travelSubjects as $name)
                                                    <a href="{{ route('blogs.category', ['travel', 'onderwerp' => Str::slug($name)]) }}" class="pill bg-night-600 text-cream hover:bg-brand hover:text-night-900 transition">{{ $name }}</a>
                                                @endforeach
                                            </div>
                                            <div class="mt-5 flex flex-col gap-2">
                                                <a href="{{ $travelUrl }}" class="inline-flex items-center gap-2 font-semibold text-brand hover:underline underline-offset-4">Alle reisverhalen <span aria-hidden="true">→</span></a>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="pb-3 text-xs uppercase tracking-widest font-semibold text-accent">🧭 Reizen</p>
                                            <ul class="max-h-72 overflow-y-auto pr-2">
                                                @foreach($navTrips as $menuTrip)
                                                    <li><a href="{{ $menuTrip->filterUrl() }}" class="block py-1 text-cream-muted hover:text-cream transition">{{ $menuTrip->name }}</a></li>
                                                @endforeach
                                            </ul>
                                        </div>
                                        <div>
                                            <p class="pb-3 text-xs uppercase tracking-widest font-semibold text-accent">🏙️ Citytrips</p>
                                            <ul>
                                                @foreach($navCityTrips as $menuTrip)
                                                    <li><a href="{{ $menuTrip->filterUrl() }}" class="block py-1 text-cream-muted hover:text-cream transition">{{ $menuTrip->name }}</a></li>
                                                @endforeach
                                            </ul>
                                        </div>
                                        <div>
                                            <p class="pb-3 text-xs uppercase tracking-widest font-semibold text-accent">Landen</p>
                                            <ul class="grid grid-cols-2 gap-x-3">
                                                @foreach($countries as $navCountry)
                                                    <li><a href="{{ route('blogs.blogCountry', $navCountry->id) }}" class="block py-1 text-cream-muted hover:text-cream transition">{{ $navCountry->name }}</a></li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li>
                                <a href="{{ route('blogs.map') }}" class="{{ $navLink }}"><i class="fas fa-map-marked-alt me-1 text-accent"></i>Kaart</a>
                            </li>
                        @else
                            {{-- C: Reizen is a page (trip cards + subjects); Kaart stays --}}
                            <li>
                                <a href="{{ $travelUrl }}?nav=c" class="{{ $onTravel ? $navActive : $navLink }}">Reizen</a>
                            </li>
                            <li>
                                <a href="{{ route('blogs.map') }}" class="{{ $navLink }}"><i class="fas fa-map-marked-alt me-1 text-accent"></i>Kaart</a>
                            </li>
                        @endif
                    @endif
                    @php $onLifestyle = request()->routeIs('blogs.category') && request()->route('category')?->slug === 'lifestyle'; @endphp
                    <li>
                        <a href="{{ route('blogs.category', 'lifestyle') }}" class="{{ $onLifestyle ? $navActive : $navLink }}" @if($onLifestyle) aria-current="page" @endif>Lifestyle</a>
                    </li>
                    <li>
                        <a href="{{ route('about') }}" class="{{ request()->routeIs('about') ? $navActive : $navLink }}" @if(request()->routeIs('about')) aria-current="page" @endif>Ik &amp; wij</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>
