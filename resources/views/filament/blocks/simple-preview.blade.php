<div class="flex items-start gap-3 rounded-lg bg-gray-50 p-3 dark:bg-white/5">
    <span class="text-xl leading-none" aria-hidden="true">{{ $icon }}</span>
    <div class="min-w-0">
        <p class="font-semibold text-gray-950 dark:text-white">{{ $title }}</p>
        @if(filled($text))
            <p class="mt-0.5 line-clamp-2 text-sm text-gray-600 dark:text-gray-300">{{ $text }}</p>
        @endif
    </div>
</div>
