<div class="grid grid-cols-4 gap-2 sm:grid-cols-8">
    @forelse($images as $image)
        <img src="{{ $image['thumb'] }}" alt="" class="aspect-square w-full rounded-md object-cover">
    @empty
        <p class="col-span-full text-sm text-gray-500 dark:text-gray-400">Nog geen foto's gekozen.</p>
    @endforelse
</div>
@if($total > $images->count())
    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">+ {{ $total - $images->count() }} meer</p>
@endif
