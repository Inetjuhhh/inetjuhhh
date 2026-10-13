@props([
    // Each stop: ['number' => 3, 'url' => '…', 'label' => 'Pagina 3', 'state' => 'passed'|'current'|'ahead', 'far' => bool]
    // or ['gap' => true] for skipped stops (…). A "far" stop is hidden on a phone, where only the nearby ones fit.
    'stops' => [],
])

{{-- A route of numbered stops, like the trip map: solid where you've been, dotted ahead, the current stop is the sage pin --}}
<div {{ $attributes->class('flex items-center') }}>
    @foreach ($stops as $stop)
        @if ($stop['gap'] ?? false)
            <span class="hidden h-0 w-8 border-t-2 border-dashed border-brand/40 sm:block" aria-hidden="true"></span>
            <span class="hidden px-1 text-cream-muted sm:block" aria-hidden="true">…</span>
            @continue
        @endif

        @php
            $state = $stop['state'];
            $far = $stop['far'] ?? false;
        @endphp

        {{-- The road to this stop: solid when it is behind you --}}
        @unless ($loop->first)
            <span @class([
                'h-0 w-4 border-t-2 sm:w-8',
                'hidden sm:block' => $far,
                'border-brand' => $state !== 'ahead',
                'border-dashed border-brand/40' => $state === 'ahead',
            ]) aria-hidden="true"></span>
        @endunless

        @if ($state === 'current')
            <span aria-current="page" title="{{ $stop['label'] }}"
                  class="relative flex h-11 w-11 flex-none items-center justify-center rounded-full border-[3px] border-night-900 bg-accent font-semibold text-night-900 shadow-[0_0_0_4px_rgba(157,184,148,0.35)]">
                {{ $stop['number'] }}
            </span>
        @else
            <a href="{{ $stop['url'] }}" aria-label="{{ $stop['label'] }}" title="{{ $stop['label'] }}" @class([
                'h-8 w-8 flex-none items-center justify-center rounded-full border-2 text-xs font-semibold transition hover:scale-110',
                'flex' => ! $far,
                'hidden sm:flex' => $far,
                'border-brand bg-night-700 text-cream' => $state === 'passed',
                'border-night-500 bg-night-800 text-cream-muted hover:border-brand hover:text-cream' => $state === 'ahead',
            ])>{{ $stop['number'] }}</a>
        @endif
    @endforeach
</div>
