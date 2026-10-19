<?php

use App\Models\Blog;
use App\Models\Category;
use App\Models\Trip;
use App\Models\User;

function feedBlog(string $slug, array $attributes = []): Blog
{
    return Blog::create([
        'title' => ucfirst($slug), 'slug' => $slug, 'excerpt' => "Over {$slug}", 'status' => 'published',
        'placed_by_id' => User::first()?->id ?? User::factory()->create()->id,
        'content' => ['type' => 'doc', 'content' => []], ...$attributes,
    ]);
}

it('lists every page Google may show in the sitemap, and nothing that is not online', function () {
    $trip = Trip::create(['name' => 'Italië 2026', 'slug' => 'italie-2026', 'starts_on' => '2026-07-01', 'ends_on' => '2026-08-01']);
    $category = Category::create(['name' => 'Reizen']);
    $blog = feedBlog('toscane', ['trip_id' => $trip->id]);
    $blog->categories()->attach($category);
    feedBlog('concept', ['status' => 'draft']);
    feedBlog('later', ['published_at' => now()->addWeek()]);

    $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8')->getContent();
    // Compare the paths: the test request runs on another host than route() builds
    $path = fn (string $url) => (parse_url($url, PHP_URL_PATH) ?? '/') . (parse_url($url, PHP_URL_QUERY) ? '?' . parse_url($url, PHP_URL_QUERY) : '');
    $urls = [];
    foreach (simplexml_load_string($xml)->url as $url) {
        $urls[] = $path((string) $url->loc);
    }

    expect($urls)->toContain('/', '/blogs/toscane', $path(route('blogs.category', $category)), '/?reis=italie-2026', '/privacy')
        ->not->toContain('/blogs/concept')
        ->not->toContain('/blogs/later');
});

it('offers the newest blogs as an RSS feed, also on the old WordPress address', function () {
    feedBlog('oud', ['published_at' => now()->subWeek()]);
    feedBlog('nieuw', ['published_at' => now()->subDay()]);
    feedBlog('concept', ['status' => 'draft']);

    $rss = simplexml_load_string($this->get('/feed')->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8')->getContent());

    $titles = [];
    foreach ($rss->channel->item as $item) {
        $titles[] = (string) $item->title;
    }
    expect($titles)->toBe(['Nieuw', 'Oud']);
    $this->get('/feed/')->assertOk();
    $this->get('/')->assertSee('type="application/rss+xml"', false);
});

it('opens the live site to search engines, and keeps them out of local and test copies', function () {
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /')->assertDontSee('Sitemap:');

    app()->detectEnvironment(fn () => 'production');

    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin')->assertSee('Sitemap: ' . route('sitemap'));
});

it('gives a trip page its own address for Google, not the homepage', function () {
    $trip = Trip::create(['name' => 'Italië 2026', 'slug' => 'italie-2026', 'starts_on' => '2026-07-01', 'ends_on' => '2026-08-01']);
    feedBlog('toscane', ['trip_id' => $trip->id]);

    $this->get($trip->filterUrl() . '&utm_source=nieuwsbrief')
        ->assertSee('<link rel="canonical" href="' . $trip->filterUrl() . '">', false);
});
