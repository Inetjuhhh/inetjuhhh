{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
{{-- The 20 newest blogs, for readers who follow blogs in an app (Feedly, NetNewsWire, …): FeedController::rss --}}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/">
    <channel>
        <title>Inetjuhhh</title>
        <link>{{ route('blogs.index') }}</link>
        <description>{{ \App\View\Components\AppLayout::DESCRIPTION }}</description>
        <language>nl</language>
        <atom:link href="{{ route('feed') }}" rel="self" type="application/rss+xml"/>
@if($blogs->isNotEmpty())
        <lastBuildDate>{{ $blogs->first()->publishedDate()->toRssString() }}</lastBuildDate>
@endif
@foreach($blogs as $blog)
        <item>
            <title>{{ $blog->title }}</title>
            <link>{{ route('blogs.show', $blog) }}</link>
            <guid isPermaLink="true">{{ route('blogs.show', $blog) }}</guid>
            <pubDate>{{ $blog->publishedDate()->toRssString() }}</pubDate>
            <description>{{ $blog->shareDescription() }}</description>
@foreach($blog->categories as $category)
            <category>{{ $category->name }}</category>
@endforeach
@if($image = $blog->shareImageUrl())
            <media:content url="{{ $image }}" medium="image"/>
@endif
        </item>
@endforeach
    </channel>
</rss>
