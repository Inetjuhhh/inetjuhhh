<?php

use App\Models\Blog;
use App\Models\Country;
use App\Models\User;
use App\Support\RelatedBlogs;

function story(string $title, string $date, ?Country $country = null, ?array $pin = null, string $status = 'published'): Blog
{
    $blog = Blog::create([
        'title' => $title, 'slug' => str($title)->slug(), 'excerpt' => 'Intro', 'status' => $status,
        'published_at' => $date, 'placed_by_id' => User::first()?->id ?? User::factory()->create()->id,
        'content' => ['type' => 'doc', 'content' => []],
        'latitude' => $pin[0] ?? null, 'longitude' => $pin[1] ?? null,
    ]);

    if ($country) {
        $blog->countries()->attach($country);
    }

    return $blog->fresh();
}

beforeEach(function () {
    $this->thailand = Country::create(['name' => 'Thailand', 'latitude' => 15.87, 'longitude' => 100.99]);
    $this->peru = Country::create(['name' => 'Peru', 'latitude' => -9.19, 'longitude' => -75.01]);
});

it('suggests the previous and next story from the same region', function () {
    story('Bangkok', '2025-01-01', $this->thailand);
    story('Machu Picchu', '2025-02-01', $this->peru);
    $angkor = story('Angkor', '2025-03-01', pin: [13.41, 103.87]); // Cambodia, close to Thailand
    $lanta = story('Koh Lanta', '2025-04-01', $this->thailand);
    story('Cusco', '2025-05-01', $this->peru);
    story('Chiang Mai', '2025-06-01', $this->thailand);

    $related = RelatedBlogs::for($lanta);

    expect($related->previous->title)->toBe('Angkor')
        ->and($related->previousInRegion)->toBeTrue()
        ->and($related->next->title)->toBe('Chiang Mai')
        ->and($related->nextInRegion)->toBeTrue()
        // Angkor is in Cambodia: nearby, but not in Thailand
        ->and($related->previousLabel)->toBe('Vorig verhaal in de buurt')
        ->and($related->nextLabel)->toBe('Volgend verhaal uit Thailand')
        ->and($related->more->first()->title)->toBe('Bangkok')
        ->and($related->more->pluck('title'))->not->toContain('Angkor', 'Chiang Mai');

    // Koh Lanta has no pin here, so it sits in the centre of Thailand: about 410 km from Angkor
    expect(RelatedBlogs::distanceKm($angkor, $lanta))->toBeBetween(380, 450);
});

it('falls back to the previous and next story overall', function () {
    story('Bangkok', '2025-01-01', $this->thailand);
    $peru = story('Machu Picchu', '2025-02-01', $this->peru);
    story('Koh Lanta', '2025-03-01', $this->thailand);

    $related = RelatedBlogs::for($peru);

    expect($related->previous->title)->toBe('Bangkok')
        ->and($related->previousInRegion)->toBeFalse()
        ->and($related->next->title)->toBe('Koh Lanta')
        ->and($related->nextInRegion)->toBeFalse()
        ->and($related->previousLabel)->toBe('Vorig verhaal');
});

it('never suggests drafts or scheduled stories', function () {
    $current = story('Bangkok', '2025-01-01', $this->thailand);
    story('Concept', '2025-02-01', $this->thailand, status: 'draft');
    story('Ingepland', now()->addWeek()->toDateTimeString(), $this->thailand);

    $related = RelatedBlogs::for($current);

    expect($related->next)->toBeNull()
        ->and($related->more)->toBeEmpty();
});

it('shows the stories to read next under the blog', function () {
    story('Angkor', '2025-03-01', pin: [13.41, 103.87]);
    story('Koh Lanta', '2025-04-01', $this->thailand);
    story('Chiang Mai', '2025-06-01', $this->thailand);

    $this->get('/blogs/koh-lanta')
        ->assertOk()
        ->assertSee('Waar gaat de reis verder?')
        ->assertSee('Vorig verhaal in de buurt')
        ->assertSee('Volgend verhaal uit Thailand')
        ->assertSee('Angkor')
        ->assertSee('Chiang Mai');
});
