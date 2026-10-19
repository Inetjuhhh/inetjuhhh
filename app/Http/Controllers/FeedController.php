<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Country;
use App\Models\Trip;
use Illuminate\Http\Response;

/**
 * For search engines and feed readers: the sitemap (every page Google may show), robots.txt
 * (which points to it) and an RSS feed of the newest blogs. All three are built from the database
 * on every request, so a new blog is in them as soon as it is online.
 */
class FeedController extends Controller
{
    public function sitemap(): Response
    {
        $blogs = Blog::published()->latest('published_at')->get(['id', 'slug', 'updated_at', 'published_at']);
        $latest = $blogs->max('updated_at');

        $pages = collect([
            ['url' => route('blogs.index'), 'lastmod' => $latest],
            ['url' => route('blogs.map'), 'lastmod' => $latest],
            ['url' => route('about'), 'lastmod' => null],
            ['url' => route('privacy'), 'lastmod' => null],
        ]);

        // Categories, trips and countries that have something to read
        $categories = Category::whereHas('blogs', fn ($query) => $query->published())->get()
            ->map(fn (Category $category) => ['url' => route('blogs.category', $category), 'lastmod' => $latest]);
        $trips = Trip::whereHas('blogs', fn ($query) => $query->published())->get()
            ->map(fn (Trip $trip) => ['url' => $trip->filterUrl(), 'lastmod' => null]);
        $countries = Country::whereHas('blogs', fn ($query) => $query->published())->get()
            ->map(fn (Country $country) => ['url' => route('blogs.blogCountry', $country->id), 'lastmod' => null]);

        $posts = $blogs->map(fn (Blog $blog) => ['url' => route('blogs.show', $blog), 'lastmod' => $blog->updated_at]);

        return response()
            ->view('feeds.sitemap', ['pages' => $pages->concat($categories)->concat($trips)->concat($countries)->concat($posts)])
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }

    public function rss(): Response
    {
        $blogs = Blog::published()->latest('published_at')->with(['featuredImage', 'categories'])->take(20)->get();

        return response()
            ->view('feeds.rss', ['blogs' => $blogs])
            ->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }

    public function robots(): Response
    {
        // Only the live site is open to search engines; a local or test copy keeps them out
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Disallow: /admin', '', 'Sitemap: ' . route('sitemap')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines) . "\n")->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
