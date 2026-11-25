{{--
    Readers per country; a click on a country shows its cities (Statistics::countries). The first
    country is open. "Toon meer" shows more countries, and more cities within a country. Cities are
    an estimate, and the location data comes from DB-IP (CC BY 4.0).
--}}
@php($firstCountries = 10)
@php($firstCities = 8)
@if ($countries->isEmpty())
    <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
        Nog geen lezers in deze periode{{ \App\Support\ViewCounter::countingSince() ? '' : ': landen worden geteld vanaf de nieuwe telling' }}.
    </p>
@else
    <div x-data="{ shown: {{ $firstCountries }} }" wire:key="countries-{{ md5($countries->toJson()) }}">
        <div class="divide-y divide-gray-100 dark:divide-white/5">
            @foreach ($countries as $country)
                <div x-data="{ open: @js($loop->first), cities: {{ $firstCities }} }" class="py-2"
                     @if ($loop->index >= $firstCountries) x-show="shown > {{ $loop->index }}" x-cloak @endif
                     wire:key="country-{{ $country['code'] }}-{{ $loop->index }}">
                    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()"
                            class="w-full rounded text-start focus-visible:outline-2 focus-visible:outline-primary-600">
                        @include('filament.widgets.partials.bars', ['rows' => [[
                            'label' => $country['name'],
                            'value' => $country['views'],
                        ]], 'max' => $countries->max('views')])
                    </button>
                    <div x-show="open" x-collapse class="mt-2 grid gap-2 ps-4">
                        @foreach ($country['cities'] as $city)
                            <div @if ($loop->index >= $firstCities) x-show="cities > {{ $loop->index }}" x-cloak @endif>
                                @include('filament.widgets.partials.bars', ['soft' => true, 'max' => $country['views'], 'rows' => [[
                                    'label' => $city['name'],
                                    'value' => $city['views'],
                                ]]])
                            </div>
                        @endforeach
                        @if (count($country['cities']) > $firstCities)
                            <button type="button" x-show="cities < {{ count($country['cities']) }}" x-on:click="cities += 10"
                                    class="justify-self-start text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">
                                Toon meer plaatsen (<span x-text="{{ count($country['cities']) }} - cities"></span>)
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if ($countries->count() > $firstCountries)
            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    <span x-text="Math.min(shown, {{ $countries->count() }})"></span> van {{ $countries->count() }} landen
                </span>
                <x-filament::button color="gray" size="sm" x-show="shown < {{ $countries->count() }}" x-on:click="shown += 10">Toon meer landen</x-filament::button>
            </div>
        @endif
    </div>
    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
        Klik op een land voor de plaatsen. Plaatsen zijn een schatting: mobiele netwerken tonen vaak een grote stad in de buurt.
        Locatiegegevens: <a href="https://db-ip.com" target="_blank" rel="noopener" class="underline">IP Geolocation by DB-IP</a>.
    </p>
@endif
