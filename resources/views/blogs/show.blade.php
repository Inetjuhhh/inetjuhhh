<x-app-layout>
    <x-slot name="header">
        <div class="container mx-auto">
            <div class="">
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                    <a href="#">
                        <img class="rounded-t-lg" src="https://picsum.photos/1500/1000" alt="" />
                    </a>
                    <div class="p-5">
                        @if($blog->categories->count() > 0 )
                            @foreach($blog->categories as $category)
                                <a href="#" class="inline-block px-3 py-1 mb-2 text-sm font-medium text-center text-white bg-green-700 rounded-lg dark:bg-green-600">{{$category->name}}</a>
                            @endforeach
                        @else
                            <a href="#" class="inline-block px-3 py-1 mb-2 text-sm font-medium text-center text-white bg-green-700 rounded-lg dark:bg-green-600">Geen categorieën</a>
                        @endif
                        <a href="#">
                            <h5 class="mb-2 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{$blog->title}}</h5>
                        </a>
                        <p class="mb-3 font-normal text-gray-700 dark:text-gray-400 italic">Door: {{$blog->placed_by->name}} - {{$blog->created_at->format('d F Y, H:i')}}</p>
                        <p class="mb-3 font-normal text-gray-700 dark:text-gray-400">{{!! tiptap_converter()->asHTML($blog->content) !!}}</p>

                    </div>
                </div>
            </div>
        </div>

    </x-slot>
</x-app-layout>
