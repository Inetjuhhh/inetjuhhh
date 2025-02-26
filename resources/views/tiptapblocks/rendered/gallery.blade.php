{{-- <div>{{ $name }}{{$gallery}}</div> --}}
@php
    $images = $gallery_images;
    $count = count($images);
    $columns = $count >= 3 ? 3 : $count;
@endphp

@if($count > 0)
    <div class="grid grid-cols-{{ $columns }} gap-4 mt-4">
        @foreach($images as $image)
            <div class="overflow-hidden rounded-md shadow-md border-2">
                <img src="{{ asset('storage/' . $image) }}" class="@if($count == 1) w-2/5 @else w-full @endif h-auto object-cover rounded-md">
            </div>
        @endforeach
    </div>
@endif
