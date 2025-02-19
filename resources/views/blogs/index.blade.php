<x-app-layout>
    <x-slot name="header">
        <div class="grid grid-cols-3 gap-5">
            @foreach($blogs as $blog)
                <div class="max-w-sm bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                    <a href="#">
                        <img class="rounded-t-lg" src="https://picsum.photos/600/400" alt="" />
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
                        <p class="mb-3 font-normal text-gray-700 dark:text-gray-400 truncate italic">Door: {{$blog->placed_by->name}} - {{$blog->created_at->format('d F Y, H:i')}}</p>
                        <p class="mb-3 font-normal text-gray-700 dark:text-gray-400 truncate">{{$blog->content}}</p>
                        <a href="{{ route('blogs.show', $blog->id)}}" class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white bg-blue-700 rounded-lg hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                            Lees meer
                            <svg class="rtl:rotate-180 w-3.5 h-3.5 ms-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 10">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 5h12m0 0L9 1m4 4L9 9"/>
                            </svg>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </x-slot>
</x-app-layout>
