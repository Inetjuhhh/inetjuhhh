<?php

use App\Filament\Resources\BlogResource\Pages\EditBlog;
use App\Models\Blog;
use App\Models\Country;
use App\Models\User;
use App\Support\TravelMap;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function mapBlog(array $attributes = []): Blog
{
    return Blog::create([
        'title' => 'Verhaal', 'slug' => str()->random(8), 'excerpt' => 'Intro', 'status' => 'published',
        'placed_by_id' => User::factory()->create()->id, 'content' => ['type' => 'doc', 'content' => []],
        ...$attributes,
    ]);
}

function fakeNominatim(array $results): void
{
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response($results)]);
}

it('looks up the place of a blog when it changes', function () {
    fakeNominatim([['lat' => '28.4024938', 'lon' => '83.6999277']]);

    $blog = mapBlog(['location_name' => 'Ghorepani, Nepal']);

    expect($blog->latitude)->toBe(28.4024938)
        ->and($blog->longitude)->toBe(83.6999277);
    Http::assertSent(fn ($request) => $request['q'] === 'Ghorepani, Nepal');
});

it('keeps coordinates that were typed in by hand', function () {
    fakeNominatim([['lat' => '1', 'lon' => '1']]);

    $blog = mapBlog(['location_name' => 'Ergens', 'latitude' => 10.5, 'longitude' => 20.5]);

    expect([$blog->latitude, $blog->longitude])->toBe([10.5, 20.5]);
    Http::assertNothingSent();
});

it('falls back to the centre of the country', function () {
    fakeNominatim([]);
    $country = Country::create(['name' => 'Peru', 'latitude' => -9.19, 'longitude' => -75.0152]);

    $blog = mapBlog();
    $blog->countries()->attach($country);

    expect($blog->fresh()->mapPosition())->toBe([-9.19, -75.0152]);
});

it('only puts published blogs with a place on the map', function () {
    fakeNominatim([]);

    $live = mapBlog(['title' => 'Op de kaart', 'location_name' => 'Hué', 'latitude' => 16.46, 'longitude' => 107.59]);
    mapBlog(['title' => 'Concept', 'status' => 'draft', 'location_name' => 'Hué', 'latitude' => 16.46, 'longitude' => 107.59]);
    mapBlog(['title' => 'Zonder plek']);

    expect(TravelMap::markers()->pluck('title')->all())->toBe(['Op de kaart']);

    $this->get('/kaart')->assertOk()->assertSee('Op de kaart')->assertDontSee('Zonder plek');
    $this->get('/')->assertOk()->assertSee('Zoek je verhaal op de kaart');
});

it('keeps the looked up coordinates when the blog is saved again', function () {
    fakeNominatim([['lat' => '16.4637', 'lon' => '107.5909']]);
    $this->actingAs(User::factory()->create());
    $blog = mapBlog(['status' => 'draft']);

    $page = Livewire::test(EditBlog::class, ['record' => $blog->id])
        ->fillForm(['location_name' => 'Hué, Vietnam'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($blog->fresh()->latitude)->toBe(16.4637);

    $page->fillForm(['title' => 'Andere titel'])->call('save')->assertHasNoFormErrors();

    expect($blog->fresh())
        ->title->toBe('Andere titel')
        ->latitude->toBe(16.4637);
});

it('suggests a pin for a typed place, but never moves a pin that is already there', function () {
    fakeNominatim([['lat' => '28.4024938', 'lon' => '83.6999277']]);
    $this->actingAs(User::factory()->create());

    // No pin yet: typing a place places the suggested pin
    $page = Livewire::test(EditBlog::class, ['record' => mapBlog(['status' => 'draft'])->id])
        ->fillForm(['location_name' => 'Ghorepani, Nepal'])
        ->assertSchemaStateSet(['latitude' => 28.402494, 'longitude' => 83.699928]);

    // A pin placed by hand stays put when the place text changes
    $page->fillForm(['latitude' => 27.7, 'longitude' => 85.3])
        ->fillForm(['location_name' => 'Ergens anders'])
        ->assertSchemaStateSet(['latitude' => 27.7, 'longitude' => 85.3]);
});

it('saves the pin from the map, also when the place text changed', function () {
    fakeNominatim([['lat' => '1', 'lon' => '1']]);
    $this->actingAs(User::factory()->create());
    $blog = mapBlog(['status' => 'draft', 'location_name' => 'Kathmandu', 'latitude' => 27.7, 'longitude' => 85.3]);

    Livewire::test(EditBlog::class, ['record' => $blog->id])
        ->fillForm(['location_name' => 'Thuis aan de keukentafel', 'latitude' => 28.21, 'longitude' => 83.98])
        ->call('save')
        ->assertHasNoFormErrors();

    expect([$blog->fresh()->latitude, $blog->fresh()->longitude])->toBe([28.21, 83.98]);
});

it('keeps the pin when a renamed place cannot be found', function () {
    fakeNominatim([]);

    $blog = mapBlog(['location_name' => 'Pokhara', 'latitude' => 28.21, 'longitude' => 83.98]);
    $blog->update(['location_name' => 'Bestaat-niet-xyz']);

    expect([$blog->fresh()->latitude, $blog->fresh()->longitude])->toBe([28.21, 83.98]);
});

// Nominatim's answers per search, the way it answers with addressdetails and namedetails
function fakePlaces(): void
{
    $answers = [
        'cefalu' => ['addresstype' => 'town', 'name' => 'Cefalù', 'address' => ['town' => 'Cefalù', 'country' => 'Italië'], 'namedetails' => ['name' => 'Cefalù']],
        'london' => ['addresstype' => 'city', 'name' => 'Groot-Londen', 'address' => ['city' => 'Groot-Londen', 'country' => 'Verenigd Koninkrijk'], 'namedetails' => ['name:nl' => 'Groot-Londen']],
        'chiang mai' => ['addresstype' => 'city', 'name' => 'เทศบาลนครเชียงใหม่', 'address' => ['city' => 'เทศบาลนครเชียงใหม่', 'country' => 'Thailand'], 'namedetails' => ['name:en' => 'Chiang Mai City Municipality']],
        'peru' => ['addresstype' => 'country', 'name' => 'Peru', 'address' => ['country' => 'Peru'], 'namedetails' => []],
    ];

    Http::fake(function ($request) use ($answers) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);
        $q = mb_strtolower($query['q']);
        $key = collect(array_keys($answers))->first(fn ($name) => str_contains(\Illuminate\Support\Str::ascii($q), $name));

        return Http::response($key ? [['lat' => '38.03', 'lon' => '14.02', ...$answers[$key]]] : []);
    });
}

it('writes a place the same way every time, however it was typed', function (string $typed, ?string $tidy) {
    fakePlaces();
    Country::create(['name' => 'Groot-Brittannië']);

    expect(\App\Support\Geocoder::placeName($typed))->toBe($tidy);
})->with([
    ['cefalu', 'Cefalù, Italië'],
    ['Cefalu Sicilië', 'Cefalù, Italië'],
    ['Italië, Cefalù', 'Cefalù, Italië'],
    // Its own name, not the area OpenStreetMap finds; the country as the countries list has it
    ['London', 'London, Groot-Brittannië'],
    // Not the Thai name, nor the municipality
    ['Chiang Mai', 'Chiang Mai, Thailand'],
    ['Peru', 'Peru'],
    ['Thuis aan de keukentafel', null],
]);

it('tidies the place in the blog form, so the blog shows country – place', function () {
    fakePlaces();
    $this->actingAs(User::factory()->create());
    $blog = mapBlog(['status' => 'draft']);
    $blog->countries()->attach(Country::create(['name' => 'Italië']));

    Livewire::test(EditBlog::class, ['record' => $blog->id])
        ->fillForm(['location_name' => 'cefalu'])
        ->assertSchemaStateSet(['location_name' => 'Cefalù, Italië'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(collect($blog->fresh()->placeTags())->pluck('label')->all())->toBe(['Italië – Cefalù']);

    // A place the map doesn't know stays as typed
    Livewire::test(EditBlog::class, ['record' => $blog->id])
        ->fillForm(['location_name' => 'Thuis aan de keukentafel'])
        ->assertSchemaStateSet(['location_name' => 'Thuis aan de keukentafel']);
});
