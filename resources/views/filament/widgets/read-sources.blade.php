<x-filament-widgets::widget>
    <x-filament::section heading="Waar lezers vandaan komen"
        description="Via welke weg lezers op een verhaal kwamen. Zo zie je wat werkt: delen op Facebook, de nieuwsbrief of gevonden worden in Google.">
        <x-slot name="afterHeader">@include('filament.widgets.partials.period')</x-slot>
        @include('filament.widgets.partials.sources', ['sources' => $sources])
    </x-filament::section>
</x-filament-widgets::widget>
