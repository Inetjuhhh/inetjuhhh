<?php

use App\Models\Blog;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;

/*
| php artisan blogs:subjects: the themes of config/subjects.php on the stories.
*/

function storyWithTags(string $slug, array $tags, array $categories = [], string $status = 'published'): Blog
{
    $blog = Blog::create([
        'title' => ucfirst($slug), 'slug' => $slug, 'excerpt' => 'Intro', 'status' => $status,
        'placed_by_id' => User::first()->id, 'content' => ['type' => 'doc', 'content' => []],
    ]);
    $blog->tags()->attach(collect($tags)->map(fn (string $name) => Tag::firstOrCreate(['name' => $name])->id));
    $blog->categories()->attach(collect($categories)->map(fn (string $name) => Category::firstOrCreate(['name' => $name])->id));

    return $blog;
}

beforeEach(function () {
    User::factory()->create();
    config(['subjects' => [
        'tags' => ['familie' => 'Familie', 'huis' => 'Huis & verbouwen', 'verbouwen' => 'Huis & verbouwen'],
        'remove' => ['reizen', 'Bangkok'],
        'stories' => ['bangkok' => ['Met Sam op reis', 'Steden'], 'badkamer' => ['Klein leed']],
        'categories' => ['badkamer' => ['add' => 'Lifestyle', 'remove' => 'Food']],
        'drafts' => ['phpmyadmin_loading_error'],
    ]]);
});

it('renames old tags to themes, removes the rest and adds the themes of each story', function () {
    $trip = storyWithTags('bangkok', ['reizen', 'Bangkok', 'familie'], ['Travel']);
    $home = storyWithTags('badkamer', ['huis', 'verbouwen'], ['Food']);

    $this->artisan('blogs:subjects')->assertSuccessful();

    expect($trip->fresh()->tags->pluck('name')->sort()->values()->all())->toBe(['Familie', 'Met Sam op reis', 'Steden'])
        ->and($home->fresh()->tags->pluck('name')->sort()->values()->all())->toBe(['Huis & verbouwen', 'Klein leed'])
        ->and($home->fresh()->categories->pluck('name')->all())->toBe(['Lifestyle'])
        // Places nobody uses any more are gone
        ->and(Tag::where('name', 'Bangkok')->exists())->toBeFalse();
});

it('keeps subjects added in the admin, and changes nothing when run again', function () {
    $story = storyWithTags('bangkok', ['reizen', 'Eigen onderwerp'], ['Travel']);

    $this->artisan('blogs:subjects')->assertSuccessful();
    $after = $story->fresh()->tags->pluck('name')->sort()->values()->all();
    $this->artisan('blogs:subjects')->assertSuccessful();

    expect($after)->toContain('Eigen onderwerp')
        ->and($story->fresh()->tags->pluck('name')->sort()->values()->all())->toBe($after);
});

it('takes WordPress leftovers offline as a draft', function () {
    $leftover = storyWithTags('phpmyadmin_loading_error', []);

    $this->artisan('blogs:subjects')->assertSuccessful();

    expect($leftover->fresh()->status)->toBe('draft');
});

it('changes nothing in a dry run', function () {
    $story = storyWithTags('bangkok', ['reizen'], ['Travel']);

    $this->artisan('blogs:subjects', ['--dry-run' => true])->assertSuccessful();

    expect($story->fresh()->tags->pluck('name')->all())->toBe(['reizen']);
});

it('has no typos in the themes of the data file', function () {
    // The real file, not the small one of these tests
    $subjects = require config_path('subjects.php');
    $themes = collect($subjects['stories'])->flatten()->concat(array_values($subjects['tags']))->unique();

    // "Natuur & dieren" and "natuur & dieren" would become two bubbles
    expect($themes->map(fn ($theme) => mb_strtolower($theme))->duplicates())->toBeEmpty()
        ->and($themes->filter(fn ($theme) => $theme !== trim($theme)))->toBeEmpty()
        ->and(collect($subjects['remove'])->intersect(array_keys($subjects['tags'])))->toBeEmpty();
});
