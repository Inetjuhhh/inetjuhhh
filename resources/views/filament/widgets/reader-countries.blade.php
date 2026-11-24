<x-filament-widgets::widget>
    <x-filament::section heading="Lezers per land">
        <x-slot name="afterHeader">@include('filament.widgets.partials.period')</x-slot>
        @include('filament.widgets.partials.countries', ['countries' => $countries])
    </x-filament::section>
</x-filament-widgets::widget>
