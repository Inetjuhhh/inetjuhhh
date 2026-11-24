{{--
    Readers per country; a click on a country shows its cities (Statistics::countries). The first
    country is open. Cities are an estimate, and the location data comes from DB-IP (CC BY 4.0).
--}}
@if ($countries->isEmpty())
    <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
        Nog geen lezers in deze periode{{ \App\Support\ViewCounter::countingSince() ? '' : ': landen worden geteld vanaf de nieuwe telling' }}.
    </p>
@else
    <div class="divide-y divide-gray-100 dark:divide-white/5">
        @foreach ($countries->take(12) as $country)
            <div x-data="{ open: @js($loop->first) }" class="py-2" wire:key="country-{{ $country['code'] }}-{{ $loop->index }}">
                <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()"
                        class="w-full rounded text-start focus-visible:outline-2 focus-visible:outline-primary-600">
                    @include('filament.widgets.partials.bars', ['rows' => [[
                        'label' => $country['name'],
                        'value' => $country['views'],
                    ]], 'max' => $countries->max('views')])
                </button>
                <div x-show="open" x-collapse class="mt-2 grid gap-1 ps-4">
                    @include('filament.widgets.partials.bars', ['soft' => true, 'max' => $country['views'], 'rows' => collect($country['cities'])->take(8)->map(fn ($city) => [
                        'label' => $city['name'],
                        'value' => $city['views'],
                    ])->all()])
                    @if (count($country['cities']) > 8)
                        <p class="text-xs text-gray-500 dark:text-gray-400">en nog {{ count($country['cities']) - 8 }} plaatsen</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
        Klik op een land voor de plaatsen. Plaatsen zijn een schatting: mobiele netwerken tonen vaak een grote stad in de buurt.
        Locatiegegevens: <a href="https://db-ip.com" target="_blank" rel="noopener" class="underline">IP Geolocation by DB-IP</a>.
    </p>
@endif
