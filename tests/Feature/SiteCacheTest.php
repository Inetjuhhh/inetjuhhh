<?php

use App\Models\Blog;
use App\Models\Country;
use App\Models\Trip;
use App\Models\User;
use App\Support\NewBlogNotifier;
use App\Support\SiteCache;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->create();
    $this->vietnam = Country::create(['name' => 'Vietnam', 'latitude' => 16, 'longitude' => 107]);
});

function cachedBlog(array $attributes = []): Blog
{
    $blog = Blog::create([
        'title' => 'Hanoi', 'slug' => str()->random(8), 'excerpt' => 'Intro', 'status' => 'published',
        'placed_by_id' => test()->user->id, 'content' => ['type' => 'doc', 'content' => []],
        'latitude' => 21.03, 'longitude' => 105.85, ...$attributes,
    ]);
    $blog->countries()->attach(test()->vietnam);

    return $blog;
}

it('shows a new blog in the menu and on the map straight away', function () {
    expect(SiteCache::menuCountries())->toBeEmpty()->and(SiteCache::mapMarkers())->toBeEmpty();

    // Saving the blog makes the remembered (empty) menu and map out of date
    cachedBlog(['trip_id' => Trip::create(['name' => 'Vietnam 2022'])->id]);

    expect(SiteCache::menuCountries()->pluck('name')->all())->toBe(['Vietnam'])
        ->and(SiteCache::menuTrips()->pluck('name')->all())->toBe(['Vietnam 2022'])
        ->and(SiteCache::mapMarkers())->toHaveCount(1);
});

it('shows a planned blog once its time has come', function () {
    cachedBlog(['published_at' => now()->addHour()]);
    expect(SiteCache::mapMarkers())->toBeEmpty();

    // Nobody saves anything: the scheduler's check every five minutes notices the blog went online
    $this->travel(2)->hours();
    NewBlogNotifier::sendDue();

    expect(SiteCache::mapMarkers())->toHaveCount(1);
});

it('takes a deleted blog off the map', function () {
    $blog = cachedBlog();
    expect(SiteCache::mapMarkers())->toHaveCount(1);

    $blog->countries()->detach();
    $blog->delete();

    expect(SiteCache::mapMarkers())->toBeEmpty()->and(SiteCache::menuCountries())->toBeEmpty();
});

it('updates the related blogs when a blog changes', function () {
    $hanoi = cachedBlog();
    expect(SiteCache::related($hanoi)->more)->toBeEmpty();

    cachedBlog(['title' => 'Hoi An']);

    expect(SiteCache::related($hanoi->fresh())->more->pluck('title')->all())->toContain('Hoi An');
});
