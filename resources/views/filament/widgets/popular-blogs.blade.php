<x-filament-widgets::widget>
    <x-filament::section heading="Populairste blogs"
        description="Likes per 100 lezers laat zien welke verhalen mensen echt raken, ook als ze minder gelezen zijn.">
        <x-slot name="afterHeader">
            <div class="flex flex-wrap gap-2">
                <x-filament::tabs :contained="false">
                    @foreach (\App\Filament\Widgets\PopularBlogs::ORDERS as $key => $label)
                        <x-filament::tabs.item :active="$by === $key" wire:click="$set('by', '{{ $key }}')">{{ $label }}</x-filament::tabs.item>
                    @endforeach
                </x-filament::tabs>
                @include('filament.widgets.partials.period')
            </div>
        </x-slot>

        @if ($blogs->isEmpty() || $blogs->every(fn ($blog) => $blog->reads === 0 && $blog->likes === 0))
            <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ $by === 'rate' ? 'Nog geen blog met genoeg lezers (20) om dit uit te rekenen.' : 'Nog niets gelezen in deze periode.' }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm tabular-nums">
                    <thead>
                        <tr class="border-b border-gray-200 text-start text-xs font-medium text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <th class="py-2 pe-2 text-start">#</th>
                            <th class="py-2 pe-2 text-start">Blog</th>
                            <th class="px-2 py-2 text-end">Gelezen</th>
                            <th class="px-2 py-2 text-end">Likes</th>
                            <th class="px-2 py-2 text-end">Per 100</th>
                            <th class="ps-2 py-2 text-end">Reacties</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($blogs as $blog)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-white/5" wire:key="popular-{{ $blog->id }}">
                                <td class="py-2.5 pe-2 text-gray-500">{{ $loop->iteration }}</td>
                                <td class="py-2.5 pe-2">
                                    <a href="{{ \App\Filament\Pages\Statistics::getUrl(['blog' => $blog->id]) }}" class="font-medium text-gray-950 hover:text-primary-600 hover:underline dark:text-white dark:hover:text-primary-400">{{ $blog->title }}</a>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $blog->trip ?? 'Geen reis' }}</div>
                                </td>
                                <td class="px-2 py-2.5 text-end">{{ number_format($blog->reads, 0, ',', '.') }}</td>
                                <td class="px-2 py-2.5 text-end">{{ $blog->likes }}</td>
                                <td class="px-2 py-2.5 text-end">{{ $blog->rate !== null ? number_format($blog->rate, 1, ',', '.') : '–' }}</td>
                                <td class="ps-2 py-2.5 text-end">{{ $blog->comments }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $blogs->count() }} van {{ $total }} blogs</span>
                <div class="flex flex-wrap items-center gap-3">
                    @if ($total > $blogs->count())
                        <x-filament::button color="gray" size="sm" wire:click="showMore">Toon meer</x-filament::button>
                    @endif
                    <a href="{{ \App\Filament\Pages\Statistics::getUrl() }}" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">Alle blogs →</a>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
