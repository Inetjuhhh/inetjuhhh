@php
    $images = $gallery_images;
    $count = count($images);
    $columns = $count >= 3 ? 3 : $count;
    $slideshow;
@endphp

@if($slideshow)
    <div class="relative w-full max-w-3xl mx-auto">
        <div id="slideshow" class="relative overflow-hidden w-full h-64">
            @foreach($images as $image)
                <img src="{{ asset('storage/' . $image) }}" class="slide absolute inset-0 w-full h-64 object-cover transition-opacity duration-500">
            @endforeach
        </div>

        <button onclick="prevSlide()" class="absolute left-2 top-1/2 transform -translate-y-1/2 bg-gray-700 text-white p-2 rounded-full">&#10094;</button>
        <button onclick="nextSlide()" class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-gray-700 text-white p-2 rounded-full">&#10095;</button>
    </div>

    <script src="{{ asset('js/slideshow.js') }}"></script>

@else

    @if($count > 0)
        <div class="grid grid-cols-{{ $columns }} gap-4 mt-4">
            @foreach($images as $k => $image)
            @php
                $imageIsLandscape = false;
                $imageSize = getimagesize(storage_path('app/public/' . $image));
                if ($imageSize[0] > $imageSize[1]) {
                    $imageIsLandscape = true;
                }
            @endphp
                <div class="@if($count < 3 ) w-2/5 @else w-full @endif overflow-hidden border-2 rounded-md shadow-md {{ $imageIsLandscape ? 'col-span-2' : '' }}">
                    <img src="{{ asset('storage/' . $image) }}" class=" h-full object-cover w-full rounded-md">
                </div>
            @endforeach
        </div>
    @endif
@endif
