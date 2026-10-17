<div class="space-y-4 text-start">
    @foreach($rows as $row)
        <div class="flex items-center gap-4">
            <img src="{{ $row['existing']->thumbnail_url }}" alt="" class="h-20 w-20 flex-none rounded-lg object-cover">
            <div class="min-w-0 text-sm">
                <p class="font-medium text-gray-950 dark:text-white truncate">{{ $row['name'] }}</p>
                <p class="text-gray-500 dark:text-gray-400">
                    Al in je bibliotheek sinds {{ $row['existing']->created_at->translatedFormat('j F Y') }}
                    @if(filled($row['existing']->title) && $row['existing']->title !== $row['name'])
                        als “{{ $row['existing']->title }}”
                    @endif
                </p>
                @if($row['newName'] !== $row['name'])
                    <p class="text-gray-500 dark:text-gray-400">Toch toevoegen? Dan heet de nieuwe “{{ $row['newName'] }}”.</p>
                @endif
            </div>
        </div>
    @endforeach
</div>
