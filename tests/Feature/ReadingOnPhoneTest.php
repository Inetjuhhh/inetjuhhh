<?php

use App\Models\Blog;
use App\Models\Trip;
use App\Models\User;

function readingStory(array $attributes = []): Blog
{
    static $n = 0;
    $n++;

    return Blog::create([
        'title' => "Verhaal {$n}", 'slug' => "verhaal-{$n}", 'status' => 'published',
        'excerpt' => 'Italië zit er op, na krap 3 weekjes rondrijden…',
        'content' => ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Italië zit er op, na krap 3 weekjes rondrijden over fatsoenlijke wegen.']]],
        ]],
        'placed_by_id' => User::first()?->id ?? User::factory()->create()->id,
        'published_at' => now()->subDays(10 - $n),
        ...$attributes,
    ]);
}

it('leaves out the intro above a story that starts with the same words', function () {
    $blog = readingStory();

    expect($blog->introAboveStory())->toBeNull();
    $this->get(route('blogs.show', $blog))->assertOk()->assertSeeTextInOrder(['Italië zit er op'])
        ->assertDontSee('border-l-2 border-accent pl-5', false);
});

it('shows an intro that says something else than the start of the story', function () {
    $blog = readingStory(['excerpt' => 'Drie weken Italië in één blog: de hoogtepunten.']);

    expect($blog->introAboveStory())->toBe('Drie weken Italië in één blog: de hoogtepunten.');
    $this->get(route('blogs.show', $blog))->assertSee('Drie weken Italië in één blog');
});

it('offers the next part of a trip right under the story, before the comments', function () {
    $trip = Trip::create(['name' => 'Italië 2026', 'slug' => 'italie-2026', 'starts_on' => '2026-07-01', 'ends_on' => '2026-08-01']);
    $first = readingStory(['trip_id' => $trip->id]);
    $second = readingStory(['trip_id' => $trip->id, 'title' => 'Het volgende deel']);

    $this->get(route('blogs.show', $first))->assertOk()
        ->assertSeeInOrder(['id="verhaal"', 'Waar gaat de reis verder?', 'Het volgende deel', 'Deel dit verhaal', 'id="reacties"', 'nieuwsbrief-blog'], false);
});

it('fills the reading bar over the story itself, not the comments below it', function () {
    $this->get(route('blogs.show', readingStory()))->assertOk()
        ->assertSee("document.getElementById('verhaal').getBoundingClientRect()", false);
});

it('folds the countries in the footer away on a phone', function () {
    $this->get('/')->assertOk()->assertSee('Reisblogs per land')->assertSee('aria-controls="footer-landen"', false);
});
