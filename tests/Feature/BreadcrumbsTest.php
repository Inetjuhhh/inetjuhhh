<?php

use App\Models\Blog;
use App\Models\Category;
use App\Models\Trip;
use App\Models\User;

// What Google reads as the path to a page: the BreadcrumbList in <script type="application/ld+json">
function breadcrumbs(string $html): ?array
{
    if (! preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m)) {
        return null;
    }

    return collect(json_decode($m[1], true)['itemListElement'])->mapWithKeys(fn ($step) => [$step['name'] => $step['item']])->all();
}

function breadcrumbBlog(array $attributes = []): Blog
{
    return Blog::create([
        'title' => 'Bizarre luxe, in lagen gebouwd', 'slug' => 'bizarre-luxe', 'status' => 'published', 'excerpt' => 'Monaco.',
        'placed_by_id' => User::factory()->create()->id,
        'content' => ['type' => 'doc', 'content' => []], 'published_at' => now()->subDay(),
    ] + $attributes);
}

it('gives a story the path Home › its category › the story, with an address at every step', function () {
    $blog = breadcrumbBlog();
    $blog->categories()->attach(Category::create(['name' => 'Reizen']));

    expect(breadcrumbs($this->get(route('blogs.show', $blog))->assertOk()->getContent()))->toBe([
        'Home' => route('blogs.index'),
        'Reizen' => route('blogs.category', 'reizen'),
        'Bizarre luxe, in lagen gebouwd' => route('blogs.show', 'bizarre-luxe'),
    ]);
});

it('uses the trip instead of the category when a story is part of one', function () {
    $trip = Trip::create(['name' => 'Côte d\'Azur 2026', 'slug' => 'cote-dazur-2026']);
    $blog = breadcrumbBlog(['trip_id' => $trip->id]);
    $blog->categories()->attach(Category::create(['name' => 'Reizen']));

    expect(array_keys(breadcrumbs($this->get(route('blogs.show', $blog))->getContent())))
        ->toBe(['Home', 'Côte d\'Azur 2026', 'Bizarre luxe, in lagen gebouwd']);
});

it('gives a category page and the other pages their path, but not the home page or a search', function () {
    Category::create(['name' => 'Lifestyle']);

    expect(breadcrumbs($this->get('/categorie/lifestyle')->assertOk()->getContent()))
        ->toBe(['Home' => route('blogs.index'), 'Lifestyle' => route('blogs.category', 'lifestyle')])
        ->and(array_keys(breadcrumbs($this->get(route('blogs.map'))->getContent())))->toBe(['Home', 'Reiskaart'])
        ->and(array_keys(breadcrumbs($this->get(route('privacy'))->getContent())))->toHaveCount(2)
        ->and(breadcrumbs($this->get('/')->getContent()))->toBeNull()
        ->and(breadcrumbs($this->get('/zoeken?q=monaco')->getContent()))->toBeNull();
});
