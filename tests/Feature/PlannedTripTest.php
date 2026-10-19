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

it('counts down to every coming trip: the next one big, the ones after it underneath', function () {
    comingTrip(starts: '+40 days');
    Trip::create(['name' => 'Japan 2028', 'kind' => Trip::KIND_TRIP, 'starts_on' => now()->addDays(412)]);
    Trip::create(['name' => 'IJsland 2028', 'kind' => Trip::KIND_TRIP, 'starts_on' => now()->addDays(200)]);

    expect(collect(SiteCache::comingTrips())->pluck('name')->all())->toBe(['Costa Rica 2027', 'IJsland 2028', 'Japan 2028']);

    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['Binnenkort', 'Costa Rica 2027', '40', 'tot we vertrekken', 'Daarna', 'IJsland 2028', '200', 'Japan 2028', '412']);
});

it('shows no "Daarna" when only one trip is coming', function () {
    comingTrip(starts: '+40 days');

    $this->get('/')->assertSee('Costa Rica 2027')->assertDontSee('Daarna');
});

it('puts a coming trip with one stop on the map too, as a circle with its name', function () {
    comingTrip([['name' => 'San José, Costa Rica']]);
    Trip::create(['name' => 'Japan 2028', 'kind' => Trip::KIND_TRIP, 'starts_on' => now()->addYear(), 'route' => [['name' => 'Tokio'], ['name' => 'Kyoto']]]);
    Trip::create(['name' => 'Nog geen plan', 'kind' => Trip::KIND_TRIP, 'starts_on' => now()->addYears(2)]);

    expect(SiteCache::plannedRoutes()->pluck('name')->all())->toBe(['Costa Rica 2027', 'Japan 2028']);
});

it('keeps a stop placed by hand on the little map, and does not look it up again', function () {
    // The map in the form gives a pin placed by hand to the name next to it (found_for)
    $trip = comingTrip([['name' => 'Ons huisje in de jungle', 'lat' => 9.123456, 'lng' => -83.654321, 'found_for' => 'Ons huisje in de jungle']]);

    Http::assertNothingSent();
    expect($trip->fresh()->route[0])->toMatchArray(['lat' => 9.123456, 'lng' => -83.654321]);
});

it('does not let a trip end before it starts, and says so in Dutch', function () {
    $this->actingAs(App\Models\User::factory()->create());

    Livewire\Livewire::test(App\Filament\Resources\TripResource\Pages\ManageTrips::class)
        ->callAction('create', ['name' => 'Japan 2027', 'kind' => Trip::KIND_TRIP, 'starts_on' => '2027-08-10', 'ends_on' => '2027-08-01'])
        ->assertHasActionErrors(['ends_on']);

    expect(Trip::where('name', 'Japan 2027')->exists())->toBeFalse()
        ->and(__('validation.after_or_equal', ['attribute' => 'tot', 'date' => 'van']))->toBe('Tot mag niet vóór van liggen.');
});

it('allows a trip of one day', function () {
    $this->actingAs(App\Models\User::factory()->create());

    Livewire\Livewire::test(App\Filament\Resources\TripResource\Pages\ManageTrips::class)
        ->callAction('create', ['name' => 'Dagje Antwerpen', 'kind' => Trip::KIND_CITY, 'starts_on' => '2027-08-10', 'ends_on' => '2027-08-10'])
        ->assertHasNoActionErrors();
});
