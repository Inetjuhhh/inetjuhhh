<?php

use App\Models\Blog;
use App\Models\Category;
use App\Models\Trip;
use App\Models\User;

/*
| The Reizen page: in the menu, the trips as cards with the way to the map, and the old address.
*/

beforeEach(function () {
    User::factory()->create();
    $this->reizen = Category::create(['name' => 'Reizen']);
    $this->trip = Trip::create(['name' => 'Vietnam 2022', 'slug' => 'vietnam-2022', 'kind' => Trip::KIND_TRIP, 'starts_on' => '2022-07-01']);

    $blog = Blog::create([
        'title' => 'Vietnam met Sam', 'slug' => 'vietnam-met-sam', 'excerpt' => 'Intro', 'status' => 'published',
        'placed_by_id' => User::first()->id, 'content' => ['type' => 'doc', 'content' => []], 'trip_id' => $this->trip->id,
    ]);
    $blog->categories()->attach($this->reizen);
});

it('has Reizen in the menu instead of the trip, city trip and country lists', function () {
    $this->get('/')
        ->assertSee(route('blogs.category', 'reizen'), false)
        ->assertDontSee('dropdownTrips', false)
        ->assertDontSee('dropdownCityTrips', false)
        ->assertDontSee('dropdownCountries', false);
});

it('shows the trips as cards on the Reizen page', function () {
    $this->get(route('blogs.category', 'reizen'))
        ->assertOk()
        ->assertSee('<h1 class="mt-2 font-display text-4xl sm:text-5xl text-cream">', false)
        ->assertSee('Reizen')
        ->assertSee('Mijn reizen')
        ->assertSee('Vietnam 2022')
        ->assertSee($this->trip->filterUrl(), false);
});

it('invites to search on the map with a small map of all pins', function () {
    // Without places there is nothing to show on a map yet
    $this->get(route('blogs.category', 'reizen'))->assertDontSee('Zoeken op de kaart?');

    Blog::first()->update(['latitude' => 16.05, 'longitude' => 108.2, 'location_name' => 'Hoi An']);
    App\Support\SiteCache::forget();

    $this->get(route('blogs.category', 'reizen'))
        ->assertSee('Zoeken op de kaart?')
        ->assertSee('travelMap({ markers:', false);
});

it('lights up Reizen in the menu on a trip', function () {
    $this->get($this->trip->filterUrl())
        ->assertSeeInOrder(['aria-current="page"', '>Reizen<'], false);
});

it('sends the old travel address to the Reizen page', function () {
    $this->get('/categorie/travel')->assertRedirect('/categorie/reizen')->assertStatus(301);
});

function tripCardPhoto(string $name): Awcodes\Curator\Models\Media
{
    return Awcodes\Curator\Models\Media::create(['disk' => 'public', 'directory' => 'uitgelicht', 'visibility' => 'public', 'name' => $name,
        'path' => "uitgelicht/{$name}.webp", 'width' => 1200, 'height' => 800, 'size' => 1000, 'type' => 'image/webp', 'ext' => 'webp']);
}

function tripCardStory(Trip $trip, string $title, int $daysAgo): Blog
{
    return Blog::create([
        'title' => $title, 'slug' => str($title)->slug(), 'excerpt' => 'Intro', 'status' => 'published', 'published_at' => now()->subDays($daysAgo),
        'placed_by_id' => User::first()->id, 'content' => ['type' => 'doc', 'content' => []], 'trip_id' => $trip->id,
        'featured_image_id' => tripCardPhoto(str($title)->slug())->id,
    ]);
}

it('shows the featured photo of the story halfway through the trip, unless one was chosen', function () {
    $trip = Trip::create(['name' => 'Thailand 2025', 'kind' => Trip::KIND_TRIP]);
    tripCardStory($trip, 'Inpakken', 30);
    $middle = tripCardStory($trip, 'Chiang Mai', 20);
    $last = tripCardStory($trip, 'Thuiskomen', 10);

    expect($trip->fresh()->coverMedia()->id)->toBe($middle->featured_image_id);

    $trip->update(['cover_image_id' => $last->featured_image_id]);
    expect($trip->fresh()->coverMedia()->id)->toBe($last->featured_image_id);

    $this->get(route('blogs.category', 'reizen'))->assertSee('thuiskomen.webp', false);
});

it('lets the photo of a trip card be chosen in the admin', function () {
    $this->actingAs(User::first());
    $trip = Trip::create(['name' => 'Thailand 2025', 'kind' => Trip::KIND_TRIP]);
    tripCardStory($trip, 'Inpakken', 30);
    $chosen = tripCardStory($trip, 'Koh Samui', 10);

    Livewire\Livewire::test(App\Filament\Resources\TripResource\Pages\ManageTrips::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$trip])
        ->callTableAction('edit', $trip, data: ['cover_image_id' => $chosen->featured_image_id])
        ->assertHasNoTableActionErrors();

    expect($trip->fresh()->cover_image_id)->toBe($chosen->featured_image_id)
        ->and(App\Filament\Resources\TripResource::coverOptions($trip->fresh()))->toHaveKeys([$chosen->featured_image_id]);
});

it('says in the admin list whether a trip photo was chosen', function () {
    $this->actingAs(User::first());
    $auto = Trip::create(['name' => 'Peru 2019', 'kind' => Trip::KIND_TRIP]);
    $chosen = Trip::create(['name' => 'Rome 2022', 'kind' => Trip::KIND_CITY]);
    $chosen->update(['cover_image_id' => tripCardStory($chosen, 'Colosseum', 5)->featured_image_id]);

    Livewire\Livewire::test(App\Filament\Resources\TripResource\Pages\ManageTrips::class)
        ->assertTableColumnStateSet('cover_choice', 'Automatisch', $auto)
        ->assertTableColumnStateSet('cover_choice', 'Zelf gekozen', $chosen);
});
