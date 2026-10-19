<x-app-layout :title="$page->title">
    <x-slot name="header">
        <p class="text-xs uppercase tracking-widest font-semibold text-accent">{{ $eyebrow ?? 'Over deze blog' }}</p>
        <h1 class="mt-2 font-display text-4xl sm:text-5xl text-cream">{{ $page->title }}</h1>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        <article class="mt-10 max-w-none prose prose-lg prose-night prose-headings:font-display prose-a:underline-offset-4 prose-img:rounded-xl">
            {!! $page->renderContent() !!}
        </article>

        @if($subscribe ?? true)
            @include('blogs.partials.subscribe', ['variant' => 'blog'])
        @endif
    </div>
</x-app-layout>
