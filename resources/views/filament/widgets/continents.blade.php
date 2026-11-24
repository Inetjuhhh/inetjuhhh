<x-filament-widgets::widget>
    <x-filament::section heading="Per continent" description="Blogs per continent, met hoe vaak ze gelezen zijn. Een blog over twee continenten telt voor allebei.">
        @if ($continents->isEmpty())
            <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Nog geen blogs met een land.</p>
        @else
            @include('filament.widgets.partials.bars', ['rows' => $continents->map(fn ($continent) => [
                'label' => $continent->name,
                'value' => $continent->blogs,
                'note' => number_format($continent->reads, 0, ',', '.') . ' gelezen',
            ])->all()])
        @endif
        @if ($missing->isNotEmpty())
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Nog zonder continent: {{ $missing->join(', ', ' en ') }}. Kies het bij
                <a href="{{ \App\Filament\Resources\CountryResource::getUrl('index') }}" class="underline">Landen</a>.
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
