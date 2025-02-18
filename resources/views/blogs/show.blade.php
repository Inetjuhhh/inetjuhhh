<x-app-layout>
    <x-slot name="header">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <h1>Blog</h1>

                    <div class="row">
                        {{$blog->title}}
                    </div>
                </div>
            </div>
        </div>

    </x-slot>
</x-app-layout>
