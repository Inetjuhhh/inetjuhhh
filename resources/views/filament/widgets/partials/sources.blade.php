{{-- Readers per source, with their share (Statistics::sources) --}}
@if ($sources->isEmpty())
    <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Nog geen lezers in deze periode.</p>
@else
    @php($total = max(1, $sources->sum('views')))
    @include('filament.widgets.partials.bars', ['rows' => $sources->map(fn ($source) => [
        'label' => $source['label'],
        'value' => $source['views'],
        'note' => round($source['views'] / $total * 100) . '%',
    ])->all()])
@endif
