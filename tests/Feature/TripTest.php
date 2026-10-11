<?php

use App\Filament\Resources\BlogResource\Pages\EditBlog;
use App\Models\Blog;
use App\Models\Country;
use App\Models\Trip;
use App\Models\User;
use App\Support\RelatedBlogs;
use Livewire\Livewire;

function tripStory(string $title, string $date, ?Trip $trip = null, ?Country $country = null, string $status = 'published'): Blog
{
    $blog = Blog::create([
        'title' => $title, 'slug' => str($title)->slug(), 'excerpt' => 'Intro', 'status' => $status,
        'published_at' => $date, 'placed_by_id' => User::first()?->id ?? User::factory()->create()->id,
        'content' => ['type' => 'doc', 'content' => []], 'trip_id' => $trip?->id,
    ]);

    if ($country) {
        $blog->countries()->attach($country);
    }

    return $blog->fresh();
}

beforeEach(function () {
    $this->trip = Trip::create([
        'name' => 'Vietnam & Cambodja 2025', 'starts_on' => '2025-09-13', 'ends_on' => '2025-10-05',
        'description' => 'Met de trein door Vietnam.',
    ]);
});

it('gives a trip a unique link and a readable period', function () {
    $twin = Trip::create(['name' => 'Vietnam & Cambodja 2025']);

    expect($this->trip->slug)->toBe('vietnam-cambodja-2025')
        ->and($twin->slug)->toBe('vietnam-cambodja-2025-2')
        ->and($this->trip->period())->toBe('september – oktober 2025');
});

it('lists all parts of a trip in reading order', function () {
    tripStory('Angkor', '2025-11-27', $this->trip);
    tripStory('Hanoi naar Hue', '2025-10-03', $this->trip);
    tripStory('Concept', '2025-12-01', $this->trip, status: 'draft');
    tripStory('Texel', '2026-05-24');

    $this->get('/?reis=vietnam-cambodja-2025')
        ->assertOk()
        ->assertSee('Vietnam &amp; Cambodja 2025', false)
        ->assertSee('Met de trein door Vietnam.')
        ->assertSee('2 delen')
        ->assertSeeInOrder(['Deel 1', 'Hanoi naar Hue', 'Deel 2', 'Angkor'])
        ->assertDontSee('Texel')
        ->assertDontSee('Concept');

    $this->get('/?reis=bestaat-niet')->assertNotFound();
});

it('shows where a story sits in its trip', function () {
    tripStory('Hanoi naar Hue', '2025-10-03', $this->trip);
    tripStory('Angkor', '2025-11-27', $this->trip);

    $this->get('/blogs/angkor')
        ->assertOk()
        ->assertSee('deel 2 van 2')
        ->assertSee('Alle delen')
        ->assertSee('Vorig deel van deze reis');
});

it('prefers the next part of the trip over a story from the same country', function () {
    $vietnam = Country::create(['name' => 'Vietnam', 'latitude' => 14.05, 'longitude' => 108.27]);

    $hanoi = tripStory('Hanoi naar Hue', '2025-10-03', $this->trip, $vietnam);
    tripStory('Hoi An', '2025-10-20', country: $vietnam);
    tripStory('Angkor', '2025-11-27', $this->trip);

    $related = RelatedBlogs::for($hanoi);

    expect($related->next->title)->toBe('Angkor')
        ->and($related->nextLabel)->toBe('Volgend deel van deze reis')
        ->and($related->more->first()->title)->toBe('Hoi An');
});

it('can filter the travel map on a trip', function () {
    $blog = tripStory('Hanoi naar Hue', '2025-10-03', $this->trip);
    $blog->update(['latitude' => 16.46, 'longitude' => 107.59]);

    $this->get('/kaart?reis=vietnam-cambodja-2025')
        ->assertOk()
        ->assertSee('Vietnam &amp; Cambodja 2025', false)
        ->assertSee("trip: 'vietnam-cambodja-2025'", false);
});

it('links a blog to a trip in the admin', function () {
    $this->actingAs(User::factory()->create());
    $blog = tripStory('Hanoi naar Hue', '2025-10-03', status: 'draft');

    Livewire::test(EditBlog::class, ['record' => $blog->id])
        ->fillForm(['trip_id' => $this->trip->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($blog->fresh()->trip->is($this->trip))->toBeTrue();
});
