{{-- <div>{{ $name }} dshjkdshdjskhsdjkh {{$gallery}}</div> --}}
@php
    $images = $gallery_images;
    $count = count($images);
    $columns = $count >= 3 ? 3 : $count;
@endphp

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
