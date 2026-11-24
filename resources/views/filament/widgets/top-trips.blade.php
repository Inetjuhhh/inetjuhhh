<x-filament-widgets::widget>
    <x-filament::section heading="Grootste reizen" description="Aantal blogs per reis, met hoe vaak ze samen gelezen en geliked zijn.">
        @if ($trips->isEmpty())
            <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Nog geen reizen met blogs.</p>
        @else
            @php($most = max(1, $trips->max('blogs')))
            <div class="overflow-x-auto">
                <table class="w-full text-sm tabular-nums">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs font-medium text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <th class="py-2 pe-2 text-start">Reis</th>
                            <th class="px-2 py-2 text-end">Blogs</th>
                            <th class="px-2 py-2 text-end">Gelezen</th>
                            <th class="ps-2 py-2 text-end">Likes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($trips as $trip)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-white/5" wire:key="trip-{{ $trip->id }}">
                                <td class="py-2.5 pe-2 font-medium text-gray-950 dark:text-white">{{ $trip->name }}</td>
                                <td class="px-2 py-2.5 text-end whitespace-nowrap">
                                    <span class="me-1.5 inline-block h-2 rounded-e bg-primary-600 align-middle dark:bg-primary-500" style="width: {{ round($trip->blogs / $most * 56) }}px"></span>{{ $trip->blogs }}
                                </td>
                                <td class="px-2 py-2.5 text-end">{{ number_format($trip->reads, 0, ',', '.') }}</td>
                                <td class="ps-2 py-2.5 text-end">{{ $trip->likes }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
