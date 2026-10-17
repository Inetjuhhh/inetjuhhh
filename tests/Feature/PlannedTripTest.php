<?php

use App\Models\Trip;
use App\Support\SiteCache;
use Illuminate\Support\Facades\Http;

/*
| Trips still to come: their planned route (entered in the admin) on the travel map.
*/

beforeEach(function () {
    // Looking up places without the internet: every place lands on the same spot
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '10.47', 'lon' => '-84.64']])]);
});

function comingTrip(array $route = [], ?string $starts = '+300 days'): Trip
{
    return Trip::create(['name' => 'Costa Rica 2027', 'kind' => Trip::KIND_TRIP, 'starts_on' => $starts ? now()->modify($starts) : null, 'route' => $route]);
}

it('looks the planned stops up on the map when the trip is saved', function () {
    $trip = comingTrip([['name' => 'La Fortuna, Costa Rica'], ['name' => ' '], ['name' => 'San José, Costa Rica']]);

    expect($trip->fresh()->route)->toHaveCount(2)
        ->and($trip->fresh()->plannedStops()[0])->toBe(['name' => 'La Fortuna, Costa Rica', 'lat' => 10.47, 'lng' => -84.64]);
});

it('does not look a stop up again when its name stays the same', function () {
    $trip = comingTrip([['name' => 'La Fortuna, Costa Rica']]);
    Http::assertSentCount(1);

    $trip->update(['description' => 'Drie weken']);
    Http::assertSentCount(1);
});

it('knows which trips are still to come', function () {
    $coming = comingTrip();
    $past = Trip::create(['name' => 'Italië 2026', 'kind' => Trip::KIND_TRIP, 'starts_on' => now()->subMonths(3)]);

    expect(Trip::upcoming()->pluck('id')->all())->toBe([$coming->id])
        ->and($coming->isUpcoming())->toBeTrue()
        ->and($past->isUpcoming())->toBeFalse();
});

it('puts the route of a coming trip on the travel map, and not that of a past one', function () {
    comingTrip([['name' => 'San José, Costa Rica'], ['name' => 'La Fortuna, Costa Rica']]);
    Trip::create(['name' => 'Italië 2026', 'kind' => Trip::KIND_TRIP, 'starts_on' => now()->subMonths(3), 'route' => [['name' => 'Rome'], ['name' => 'Napels']]]);

    expect(SiteCache::plannedRoutes()->pluck('name')->all())->toBe(['Costa Rica 2027']);

    $this->get(route('blogs.map'))->assertOk()->assertSee('planned:', false)->assertSee('Costa Rica 2027');
});

function storyOnTrip(Trip $trip, string $title, string $when): App\Models\Blog
{
    return App\Models\Blog::create([
        'title' => $title, 'slug' => str($title)->slug(), 'excerpt' => 'Intro', 'status' => 'published', 'published_at' => $when,
        'placed_by_id' => (App\Models\User::first() ?? App\Models\User::factory()->create())->id,
        'content' => ['type' => 'doc', 'content' => []], 'trip_id' => $trip->id,
    ]);
}

it('counts down to the next trip at the bottom of the homepage', function () {
    $trip = comingTrip(starts: '+40 days');
    $trip->update(['description' => 'Drie weken regenwoud en vulkanen']);
    storyOnTrip(Trip::create(['name' => 'Italië 2026', 'kind' => Trip::KIND_TRIP, 'starts_on' => now()->subMonths(3)]), 'Toscane', now()->subMonths(3));

    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['Toscane', 'Binnenkort', 'Costa Rica 2027', 'Mail me als het eerste verhaal online staat', '40', 'dagen', 'tot we vertrekken'])
        ->assertSee('Drie weken regenwoud en vulkanen');
});

it('stops counting down once the first story of the trip is online', function () {
    $trip = comingTrip(starts: '+10 days');
    storyOnTrip($trip, 'We pakken de koffers', now()->subDay());

    $this->get('/')->assertDontSee('tot we vertrekken');
});

it('shows the trips on a timeline, oldest first, with the coming one after "nu"', function () {
    $reizen = App\Models\Category::create(['name' => 'Reizen']);
    foreach ([['Spanje 2024', '-26 months'], ['Italië 2026', '-3 months']] as [$name, $ago]) {
        storyOnTrip(Trip::create(['name' => $name, 'kind' => Trip::KIND_TRIP, 'starts_on' => now()->modify($ago)]), "Verhaal {$name}", now()->modify($ago))
            ->categories()->attach($reizen);
    }
    comingTrip(starts: '+40 days');

    $this->get(route('blogs.category', 'reizen'))
        ->assertOk()
        ->assertSeeInOrder(['Mijn reizen', 'Spanje 2024', 'Italië 2026', '>nu<', 'Costa Rica 2027', 'nog 40 dagen'], false)
        // On a phone the countdown takes the place of the timeline
        ->assertSee('mb-10 sm:hidden', false)
        ->assertSee('hidden sm:block', false);
});
