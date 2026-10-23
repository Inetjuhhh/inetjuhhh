{{-- Curator's grid tile (curator::components.tables.grid-column), with where the photo is instead of its file name --}}
@php
    $record = $getRecord();
    $isSvg = curator()->isSvg($record->ext);
    $where = \App\Filament\Curator\MediaTable::placeOf($record);
@endphp

<div {{ $attributes->merge($getExtraAttributes())->class(['curator-grid-column absolute inset-0 rounded-t-xl overflow-hidden']) }}
     title="{{ $where ? $where['blog'] . ($where['more'] ? ' (en nog ' . $where['more'] . ')' : '') : $record->pretty_name }}">
    <div @class([
        'rounded-t-xl h-full overflow-hidden bg-gray-100 dark:bg-gray-950/50',
        'checkered' => $isSvg,
    ])>
        <x-curator::display
            :item="$record"
            :src="$record->mediumUrl"
            :lazy="true"
            icon-classes="size-24"
            x-on:click="toggleSelectedRecord({{ \Illuminate\Support\Js::from((string) $record->id) }})"
            @class([
                'h-full',
                'w-auto mx-auto p-2' => $isSvg,
                'w-full' => ! $isSvg,
            ])
        />

        <div class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/85 to-transparent px-2 pb-1.5 pt-10 text-xs text-white">
            @if($where)
                <p class="truncate font-medium">{{ $where['place'] ?? $where['blog'] }}</p>
                <p class="flex justify-between gap-2 text-white/80">
                    <span class="truncate">{{ $where['trip'] ?? $where['blog'] }}</span>
                    @if($where['more'])
                        <span class="shrink-0">+{{ $where['more'] }}</span>
                    @endif
                </p>
            @elseif($use = \App\Filament\Curator\MediaTable::otherUses()[$record->id] ?? null)
                <p class="truncate font-medium">{{ $use }}</p>
                <p class="truncate text-white/80">{{ $record->pretty_name }}</p>
            @else
                <p class="truncate font-medium text-amber-300">Nergens gebruikt</p>
                <p class="truncate text-white/80">{{ $record->pretty_name }}</p>
            @endif
        </div>
    </div>
</div>
