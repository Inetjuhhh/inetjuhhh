<x-app-layout>
    <x-slot name="header">
        {{-- @extends('layouts.app') --}}


        <h1>this is the blog page</h1>
        @foreach($blogs as $blog)
            <h2>{{ $blog->title }}</h2>
            <p>{{ $blog->content }}</p>
        @endforeach
    </x-slot>
</x-app-layout>
