<?php

use App\Models\Blog;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;

/*
| The subjects of a category page (the bubbles): which ones, how many stories, and filtering on one.
*/

function storyAbout(string $title, Category $category, array $tags, string $status = 'published'): Blog
{
    $blog = Blog::create([
        'title' => $title, 'slug' => str($title)->slug(), 'excerpt' => 'Intro', 'status' => $status,
        'placed_by_id' => User::first()->id, 'content' => ['type' => 'doc', 'content' => []],
    ]);
    $blog->categories()->attach($category);
    $blog->tags()->attach(collect($tags)->map(fn (string $name) => Tag::firstOrCreate(['name' => $name])->id));

    return $blog;
}

beforeEach(function () {
    User::factory()->create();
    $this->lifestyle = Category::create(['name' => 'Lifestyle']);
    $travel = Category::create(['name' => 'Travel']);

    storyAbout('Sam wordt drie', $this->lifestyle, ['familie', 'Sam']);
    storyAbout('Pootje omhoog', $this->lifestyle, ['familie', 'Klein leed']);
    storyAbout('Nog een concept', $this->lifestyle, ['familie', 'geheim'], status: 'draft');
    storyAbout('Lissabon', $travel, ['familie', 'stedentrip']);
});

it('shows the subjects of the published stories in this category, with their numbers', function () {
    $response = $this->get(route('blogs.category', 'lifestyle'))->assertOk();

    expect($response->viewData('subjects')->pluck('count', 'name')->all())->toBe(['Familie' => 2, 'Sam' => 1, 'Klein leed' => 1]);
    $response->assertSee('Waar schrijf ik over?')
        // The links work without JavaScript too
        ->assertSee(route('blogs.category', ['lifestyle', 'onderwerp' => 'klein-leed']), false)
        ->assertDontSee('Geheim')
        ->assertDontSee('Stedentrip');
});

it('shows only the stories about the chosen subject', function () {
    $this->get(route('blogs.category', ['lifestyle', 'onderwerp' => 'sam']))
        ->assertOk()
        ->assertSee('Verhalen over')
        ->assertSee('Alle onderwerpen')
        ->assertSee('Sam wordt drie')
        ->assertDontSee('Pootje omhoog');
});

it('shows every story for a subject that does not exist', function () {
    $this->get(route('blogs.category', ['lifestyle', 'onderwerp' => 'bestaat-niet']))
        ->assertOk()
        ->assertSee('Sam wordt drie')
        ->assertSee('Pootje omhoog')
        ->assertDontSee('Alle onderwerpen');
});

it('leaves the bubbles out when there is nothing to choose', function () {
    $food = Category::create(['name' => 'Food']);
    storyAbout('Pad thai', $food, ['koken']);

    $this->get(route('blogs.category', 'food'))->assertOk()->assertDontSee('Waar schrijf ik over?');
});

it('shows at most the 20 subjects written about most, plus the chosen one', function () {
    $many = Category::create(['name' => 'Reizen']);
    foreach (range(1, 25) as $i) {
        storyAbout("Reis {$i}", $many, array_map(fn ($j) => "plek {$j}", range($i, 25)));
    }

    $top = $this->get(route('blogs.category', 'reizen'))->viewData('subjects');
    expect($top)->toHaveCount(20)->and($top->first()['name'])->toBe('Plek 25');

    $chosen = $this->get(route('blogs.category', ['reizen', 'onderwerp' => 'plek-1']))->viewData('subjects');
    expect($chosen)->toHaveCount(21)->and($chosen->last())->toMatchArray(['name' => 'Plek 1', 'active' => true]);
});
