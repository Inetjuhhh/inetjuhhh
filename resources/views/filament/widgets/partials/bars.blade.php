{{--
    A list of horizontal bars: a name, a bar to scale, and the number. $rows: list of
    ['label' => string, 'value' => int, 'note' => ?string]; $soft: lighter bars (for the cities);
    $max: the number a full bar stands for (default: the biggest row).
--}}
@php($max = max(1, $max ?? collect($rows)->max('value')))
<div class="grid gap-2">
    @foreach ($rows as $row)
        <div class="grid grid-cols-[minmax(0,9rem)_minmax(0,1fr)_auto] items-center gap-3 text-sm"
             title="{{ $row['label'] }}: {{ number_format($row['value'], 0, ',', '.') }}{{ isset($row['note']) ? ' · ' . $row['note'] : '' }}">
            <span class="truncate {{ ($soft ?? false) ? 'text-gray-600 dark:text-gray-400' : 'text-gray-950 dark:text-white' }}">{{ $row['label'] }}</span>
            <span class="h-3">
                <span @class([
                    'block h-full rounded-e',
                    'bg-primary-200 dark:bg-primary-900' => $soft ?? false,
                    'bg-primary-600 dark:bg-primary-500' => ! ($soft ?? false),
                ]) style="width: {{ max(1, round($row['value'] / $max * 100, 1)) }}%"></span>
            </span>
            <span class="text-end tabular-nums text-gray-700 dark:text-gray-300">
                {{ number_format($row['value'], 0, ',', '.') }}
                @isset($row['note'])
                    <span class="text-gray-500 dark:text-gray-400">· {{ $row['note'] }}</span>
                @endisset
            </span>
        </div>
    @endforeach
</div>
