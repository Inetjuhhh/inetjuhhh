{{-- <div>{{ $name }} dshjkdshdjskhsdjkh {{$gallery}}</div> --}}
@php
    $images = $gallery_images;
    $count = count($images);
    $columns = $count >= 3 ? 3 : $count;
    $slideshow;
@endphp

@if($slideshow)
    <div x-data="{ activeSlide: 0, images: @json($images) }" class="relative w-full max-w-3xl mx-auto">
        <!-- Slides -->
        <template x-for="(image, index) in images" :key="index">
            <div x-show="activeSlide === index" class="absolute inset-0 flex items-center justify-center transition-all duration-500 ease-in-out">
                <img :src="image" class="w-full h-64 object-cover rounded-lg shadow-lg">
            </div>
        </template>

        <!-- Navigation Buttons -->
        <button @click="activeSlide = (activeSlide === 0) ? images.length - 1 : activeSlide - 1"
                class="absolute left-0 top-1/2 transform -translate-y-1/2 bg-gray-700 text-white p-2 rounded-full shadow-lg">
            &#10094;
        </button>
        <button @click="activeSlide = (activeSlide + 1) % images.length"
                class="absolute right-0 top-1/2 transform -translate-y-1/2 bg-gray-700 text-white p-2 rounded-full shadow-lg">
            &#10095;
        </button>

        <!-- Dots for navigation -->
        <div class="absolute bottom-2 left-1/2 transform -translate-x-1/2 flex space-x-2">
            <template x-for="(image, index) in images" :key="index">
                <button @click="activeSlide = index"
                        :class="activeSlide === index ? 'bg-gray-900' : 'bg-gray-400'"
                        class="w-3 h-3 rounded-full"></button>
            </template>
        </div>
    </div>

@else
    @if($count > 0)
        <div class="grid grid-cols-{{ $columns }} gap-2 p-2 bg-gray-100 rounded-lg">
            @foreach($images as $image)
                <div class="overflow-hidden rounded-lg">
                    <img src="{{ asset('storage/' . $image) }}" class="w-full h-auto object-cover">
                </div>
            @endforeach
        </div>
    @else
        <p class="text-gray-400">No images selected.</p>
    @endif
@endif
