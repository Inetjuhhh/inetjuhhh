@php
    $images = $gallery_images;
    $count = count($images);
    $columns = $count >= 3 ? 3 : $count;
    // Full class names so Tailwind picks them up
    $gridClass = [1 => 'grid-cols-1', 2 => 'grid-cols-2', 3 => 'grid-cols-3'][$columns] ?? 'grid-cols-1';
    $slideshow;
@endphp

@if($slideshow)
    <div class="not-prose relative w-full max-w-3xl mx-auto my-8">
        <div id="slideshow" class="relative overflow-hidden w-full h-64 sm:h-96 rounded-xl shadow-soft">
            @foreach($images as $image)
                <img src="{{ asset('storage/' . $image) }}" class="slide absolute inset-0 w-full h-full object-cover transition-opacity duration-700">
            @endforeach
        </div>

        <button onclick="prevSlide()" class="absolute left-2 top-1/2 transform -translate-y-1/2 w-10 h-10 flex items-center justify-center bg-night-900/70 backdrop-blur text-cream rounded-full hover:bg-brand hover:text-night-900 transition">&#10094;</button>
        <button onclick="nextSlide()" class="absolute right-2 top-1/2 transform -translate-y-1/2 w-10 h-10 flex items-center justify-center bg-night-900/70 backdrop-blur text-cream rounded-full hover:bg-brand hover:text-night-900 transition">&#10095;</button>
    </div>

    <script src="{{ asset('js/slideshow.js') }}"></script>

@else

    @if($count > 0)
        <div class="not-prose grid {{ $gridClass }} gap-4 my-8">
            @foreach($images as $k => $image)
            @php
                $imageIsLandscape = false;
                $imageSize = getimagesize(storage_path('app/public/' . $image));
                if ($imageSize[0] > $imageSize[1]) {
                    $imageIsLandscape = true;
                }
            @endphp
                <div class="@if($count == 1) w-2/5 @else w-full @endif group overflow-hidden rounded-xl shadow-soft {{ $imageIsLandscape ? 'col-span-2' : '' }}">
                    <img src="{{ asset('storage/' . $image) }}" class="h-full object-cover w-full transition duration-700 group-hover:scale-105">
                </div>
            @endforeach
        </div>
    @endif
@endif
