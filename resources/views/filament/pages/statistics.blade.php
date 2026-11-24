<x-filament-panels::page>
    @php($nl = fn ($n) => number_format($n, 0, ',', '.'))

    @if (! $record)
        {{-- All blogs: search, filter on a trip, sort --}}
        <x-filament::section>
            <div class="mb-4 flex flex-wrap gap-3">
                <x-filament::input.wrapper class="min-w-0 flex-1" prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input type="search" wire:model.live.debounce.400ms="search" placeholder="Zoek een blog op titel of reis" aria-label="Zoek een blog" />
                </x-filament::input.wrapper>
                <x-filament::input.wrapper class="w-full sm:w-56">
                    <x-filament::input.select wire:model.live="trip" aria-label="Reis">
                        <option value="">Alle reizen</option>
                        @foreach ($trips as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            @if ($blogs->isEmpty())
                <p class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">Geen blog gevonden{{ $search !== '' ? ' voor "' . $search . '"' : '' }}.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm tabular-nums">
                        <thead>
                            <tr class="border-b border-gray-200 text-xs font-medium text-gray-500 dark:border-white/10 dark:text-gray-400">
                                <th class="py-2 pe-2 text-start">Blog</th>
                                @foreach (\App\Filament\Pages\Statistics::SORTS as $key => $label)
                                    <th class="px-2 py-2 text-end">
                                        <button type="button" wire:click="$set('sort', '{{ $key }}')" @class(['whitespace-nowrap hover:text-gray-950 dark:hover:text-white', 'text-gray-950 dark:text-white' => $sort === $key])
                                                aria-sort="{{ $sort === $key ? 'descending' : 'none' }}">
                                            {{ $label }}{{ $sort === $key ? ' ↓' : '' }}
                                        </button>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($blogs->take($shown) as $row)
                                <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5" wire:key="row-{{ $row->id }}">
                                    <td class="py-2.5 pe-2">
                                        <a href="{{ \App\Filament\Pages\Statistics::getUrl(['blog' => $row->id]) }}" class="font-medium text-gray-950 hover:text-primary-600 hover:underline dark:text-white dark:hover:text-primary-400">{{ $row->title }}</a>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $row->trip ?? 'Geen reis' }}@if ($row->published_at) · {{ \Illuminate\Support\Carbon::parse($row->published_at)->translatedFormat('j M Y') }}@endif
                                        </div>
                                    </td>
                                    <td class="px-2 py-2.5 text-end">{{ $nl($row->reads) }}</td>
                                    <td class="px-2 py-2.5 text-end">{{ $row->likes }}</td>
                                    <td class="px-2 py-2.5 text-end">{{ $row->rate !== null ? number_format($row->rate, 1, ',', '.') : '–' }}</td>
                                    <td class="ps-2 py-2.5 text-end">{{ $row->comments }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ min($shown, $blogs->count()) }} van {{ $blogs->count() }} blogs</span>
                    @if ($blogs->count() > $shown)
                        <x-filament::button color="gray" size="sm" wire:click="showMore">Toon meer</x-filament::button>
                    @endif
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Alle cijfers sinds de blog online staat. "Per 100" is het aantal likes per 100 lezers, vanaf 20 lezers.
                </p>
            @endif
        </x-filament::section>
    @else
        {{-- One blog --}}
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
            <a href="{{ \App\Filament\Pages\Statistics::getUrl() }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">← Alle blogs</a>
            <span class="text-gray-500 dark:text-gray-400">
                {{ $record->published_at?->translatedFormat('j F Y') }} · {{ $record->trip?->name ?? 'Geen reis' }}
            </span>
            <a href="{{ \App\Filament\Resources\BlogResource::getUrl('edit', ['record' => $record]) }}" class="text-gray-600 hover:underline dark:text-gray-300">Bewerken</a>
            <a href="{{ route('blogs.show', $record->slug) }}" target="_blank" rel="noopener" class="text-gray-600 hover:underline dark:text-gray-300">Bekijk op de site ↗</a>
        </div>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ([
                ['Gelezen', $nl($numbers->reads), 'sinds publicatie'],
                ['Likes', $numbers->likes, $numbers->rate !== null ? number_format($numbers->rate, 1, ',', '.') . ' per 100 lezers' : 'per 100 lezers: vanaf 20 lezers'],
                ['Reacties', $numbers->comments, 'goedgekeurd'],
                ['Plek in de ranglijst', '#' . $rank, "meest gelezen van {$total} blogs"],
            ] as [$label, $value, $note])
                <x-filament::section compact>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</div>
                    <div class="mt-1 text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $value }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $note }}</div>
                </x-filament::section>
            @endforeach
        </div>

        <x-filament::section heading="Gelezen per dag" :description="count($perDay) >= 90 ? 'Laatste 90 dagen' : 'Sinds de blog online staat'">
            @php($most = max(1, max($perDay ?: [0])))
            <div class="flex h-40 items-end gap-px border-b border-gray-200 dark:border-white/10" role="img"
                 aria-label="Gelezen per dag, met een piek van {{ $most }} op één dag">
                @foreach ($perDay as $date => $views)
                    <div class="flex h-full min-w-0 flex-1 items-end justify-center" title="{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('D j M') }}: {{ $views }} keer gelezen">
                        <span class="block w-full max-w-6 rounded-t bg-primary-600 dark:bg-primary-500" style="height: {{ $views ? max(2, round($views / $most * 100)) : 0 }}%"></span>
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>{{ \Illuminate\Support\Carbon::parse(array_key_first($perDay))->translatedFormat('j M') }}</span>
                <span>vandaag</span>
            </div>
        </x-filament::section>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-filament::section heading="Lezers per land">
                <x-slot name="afterHeader">@include('filament.widgets.partials.period')</x-slot>
                @include('filament.widgets.partials.countries', ['countries' => $countries])
            </x-filament::section>

            <x-filament::section heading="Waar lezers vandaan komen">
                <x-slot name="afterHeader">@include('filament.widgets.partials.period')</x-slot>
                @include('filament.widgets.partials.sources', ['sources' => $sources])
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
