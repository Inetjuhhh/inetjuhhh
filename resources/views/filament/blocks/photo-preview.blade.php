<div>
    @if($src)
        <img src="{{ $src }}" alt="" class="max-h-72 w-full rounded-lg object-cover">
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">Nog geen foto gekozen.</p>
    @endif
    @if(filled($caption))
        <p class="mt-2 text-center text-sm italic text-gray-600 dark:text-gray-300">{{ $caption }}</p>
    @endif
</div>
