{{-- "30 dagen / Jaar / Altijd" above a statistics widget (see Concerns\HasPeriod) --}}
<x-filament::tabs :contained="false">
    @foreach (\App\Support\Statistics::PERIODS as $key => $label)
        <x-filament::tabs.item :active="$this->period === (string) $key" wire:click="$set('period', '{{ $key }}')">
            {{ $label }}
        </x-filament::tabs.item>
    @endforeach
</x-filament::tabs>
