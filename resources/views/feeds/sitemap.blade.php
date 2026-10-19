{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
{{-- Every page Google may show (FeedController::sitemap). Submit /sitemap.xml once in Google Search Console. --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($pages as $page)
    <url>
        <loc>{{ $page['url'] }}</loc>
@if($page['lastmod'])
        <lastmod>{{ $page['lastmod']->toAtomString() }}</lastmod>
@endif
    </url>
@endforeach
</urlset>
