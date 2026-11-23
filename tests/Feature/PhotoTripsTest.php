<?php

use App\Filament\Curator\EditMedia;
use App\Filament\Curator\ListMedia;
use App\Livewire\CuratorPanel;
use App\Models\Blog;
use App\Models\Trip;
use App\Models\User;
use App\Support\PhotoTrips;
use Awcodes\Curator\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
| Photos belong to a trip: linked when uploading or in the media library, or else via the blog they are in.
*/

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());

    $this->italy = Trip::create(['name' => 'Italië 2026', 'starts_on' => '2026-07-20', 'ends_on' => '2026-08-10']);
    $this->japan = Trip::create(['name' => 'Japan 2025', 'starts_on' => '2025-04-01', 'ends_on' => '2025-04-20']);
});

function tripPhoto(string $name, ?Trip $trip = null): Media
{
    Storage::disk('public')->put("{$name}.jpg", 'x');

    $media = Media::create(['disk' => 'public', 'visibility' => 'public', 'name' => $name, 'path' => "{$name}.jpg",
        'type' => 'image/jpeg', 'ext' => 'jpg', 'size' => 1, 'width' => 1600, 'height' => 1200, 'title' => $name]);

    PhotoTrips::link([$media->id], $trip?->id);

    return $media->refresh();
}

function tripBlog(Trip $trip, Media ...$photos): Blog
{
    return Blog::create([
        'title' => 'Verhaal ' . $trip->name, 'slug' => str($trip->name)->slug(), 'excerpt' => '', 'status' => 'published',
        'published_at' => '2026-07-30', 'placed_by_id' => User::first()->id, 'trip_id' => $trip->id,
        'content' => ['type' => 'doc', 'content' => array_map(
            fn (Media $photo) => ['type' => 'customBlock', 'attrs' => ['id' => 'photo', 'config' => ['media' => $photo->id]]],
            $photos,
        )],
    ]);
}

function tripPanel(?Trip $trip)
{
    return Livewire::test(CuratorPanel::class, ['settings' => [
        'diskName' => 'public', 'visibility' => 'public', 'isMultiple' => true,
        'acceptedFileTypes' => ['image/jpeg', 'image/png', 'image/webp'], 'tripId' => $trip?->id,
    ]]);
}

it('counts a photo for its own trip, or else for the trip of its blog', function () {
    $linked = tripPhoto('gekoppeld', $this->italy);
    $viaBlog = tripPhoto('via-blog');
    $choiceWins = tripPhoto('keuze-wint', $this->japan);
    $loose = tripPhoto('los');
    tripBlog($this->italy, $viaBlog, $choiceWins);

    expect(PhotoTrips::scope(Media::query(), $this->italy->id)->pluck('name')->sort()->values()->all())->toBe(['gekoppeld', 'via-blog'])
        ->and(PhotoTrips::scope(Media::query(), $this->japan->id)->pluck('name')->all())->toBe(['keuze-wint'])
        ->and(PhotoTrips::tripOf($viaBlog->refresh())->name)->toBe('Italië 2026')
        ->and(PhotoTrips::tripOf($loose))->toBeNull();
});

it('lists the latest trip first, and suggests the one that started last', function () {
    Trip::create(['name' => 'Later', 'starts_on' => now()->addYear(), 'ends_on' => now()->addYear()->addWeek()]);

    expect(array_values(PhotoTrips::options()))->toBe(['Later', 'Italië 2026', 'Japan 2025']);

    $this->travelTo('2026-08-01');
    expect(PhotoTrips::suggestion())->toBe($this->italy->id);
});

it('filters the media library on photos linked to a trip as well', function () {
    [$linked, $viaBlog, $other] = [tripPhoto('gekoppeld', $this->italy), tripPhoto('via-blog'), tripPhoto('ander', $this->japan)];
    tripBlog($this->italy, $viaBlog);

    Livewire::test(ListMedia::class)
        ->filterTable('trip', $this->italy->id)
        ->assertCanSeeTableRecords([$linked, $viaBlog])
        ->assertCanNotSeeTableRecords([$other]);
});

it('links selected photos to a trip in the media library, and unlinks them again', function () {
    [$first, $second, $untouched] = [tripPhoto('een'), tripPhoto('twee'), tripPhoto('drie')];

    Livewire::test(ListMedia::class)
        ->callTableBulkAction('linkTrip', [$first, $second], data: ['trip_id' => $this->japan->id])
        ->assertNotified();

    expect($first->refresh()->trip_id)->toBe($this->japan->id)
        ->and($second->refresh()->trip_id)->toBe($this->japan->id)
        ->and($untouched->refresh()->trip_id)->toBeNull();

    Livewire::test(ListMedia::class)->callTableBulkAction('linkTrip', [$first], data: ['trip_id' => null]);

    expect($first->refresh()->trip_id)->toBeNull();
});

it('links a batch of photos to the trip chosen when uploading them in the media library', function () {
    $old = tripPhoto('oud');

    Livewire::test(ListMedia::class)
        ->callAction('curator_multi_upload', data: [
            'trip_id' => $this->italy->id,
            'files' => [UploadedFile::fake()->image('zee.jpg', 400, 300), UploadedFile::fake()->image('berg.jpg', 400, 300)],
        ])
        ->assertHasNoActionErrors();

    expect(Media::where('trip_id', $this->italy->id)->pluck('title')->sort()->values()->all())->toBe(['berg', 'zee'])
        ->and($old->refresh()->trip_id)->toBeNull();
});

it('changes the trip on the page of one photo', function () {
    $photo = tripPhoto('zee');

    Livewire::test(EditMedia::class, ['record' => $photo->getRouteKey()])
        ->fillForm(['trip_id' => $this->italy->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($photo->refresh()->trip_id)->toBe($this->italy->id);
});

it('opens the photo window on the photos of the blog\'s trip, and can show all of them', function () {
    $linked = tripPhoto('gekoppeld', $this->italy);
    $viaBlog = tripPhoto('via-blog');
    $other = tripPhoto('ander', $this->japan);
    tripBlog($this->italy, $viaBlog);

    $panel = tripPanel($this->italy)->assertSet('panelData.trip_id', $this->italy->id);
    expect(collect($panel->get('files'))->pluck('name')->sort()->values()->all())->toBe(['gekoppeld', 'via-blog']);

    // Searching stays within the trip
    $panel->set('search', 'ander');
    expect($panel->get('files'))->toBeEmpty();

    // All photos
    $panel->set('search', '')->set('panelData.trip_id', null);
    expect(collect($panel->get('files'))->pluck('name')->sort()->values()->all())->toBe(['ander', 'gekoppeld', 'via-blog']);

    // Without a trip (a page, not a blog) it starts on all photos
    expect(tripPanel(null)->get('files'))->toHaveCount(3);
});

it('links photos uploaded in the photo window to the chosen trip', function () {
    $panel = tripPanel($this->italy)
        ->set('panelData.files_to_add', [UploadedFile::fake()->image('zee.jpg', 400, 300)])
        ->callAction('addFiles');

    expect(Media::where('title', 'zee')->first()->trip_id)->toBe($this->italy->id)
        // Ready for the next batch, on the same trip
        ->and($panel->get('panelData.trip_id'))->toEqual($this->italy->id);

    tripPanel($this->italy)
        ->set('panelData.trip_id', null)
        ->set('panelData.files_to_add', [UploadedFile::fake()->image('berg.jpg', 400, 300)])
        ->callAction('addFiles');

    expect(Media::where('title', 'berg')->first()->trip_id)->toBeNull();
});

it('puts the photos used nowhere first, in the photo window and in the media library', function () {
    $this->travelTo('2026-08-01 10:00');
    $inBlog = tripPhoto('in-blog');
    $this->travelTo('2026-08-02 10:00');
    $cover = tripPhoto('omslag');
    $this->travelTo('2026-08-03 10:00');
    $older = tripPhoto('ouder-ongebruikt');
    $this->travelTo('2026-08-04 10:00');
    $newer = tripPhoto('nieuwer-ongebruikt');
    $this->travelTo('2026-08-05 10:00');
    $newestInBlog = tripPhoto('nieuwste-in-blog');

    tripBlog($this->italy, $inBlog, $newestInBlog);
    $this->japan->update(['cover_image_id' => $cover->id]);

    // Unused first (newest first), then the rest (newest first)
    $expected = ['nieuwer-ongebruikt', 'ouder-ongebruikt', 'nieuwste-in-blog', 'omslag', 'in-blog'];

    expect(collect(tripPanel(null)->get('files'))->pluck('name')->all())->toBe($expected);

    // Searching too
    expect(collect(tripPanel(null)->set('search', 'e')->get('files'))->pluck('name')->all())
        ->toBe(['nieuwer-ongebruikt', 'ouder-ongebruikt', 'nieuwste-in-blog']);

    Livewire::test(ListMedia::class)->assertCanSeeTableRecords(
        collect($expected)->map(fn ($name) => Media::where('name', $name)->first()),
        inOrder: true,
    );
});

it('shows the trip\'s unused photos first in a blog, then the rest of the trip, then all others', function () {
    $at = function (string $day, string $name, ?Trip $trip = null): Media {
        $this->travelTo("2026-08-{$day} 10:00");

        return tripPhoto($name, $trip);
    };

    $usedOld = $at('01', 'reis-gebruikt-oud');
    $freeOld = $at('02', 'reis-ongebruikt-oud', $this->italy);
    $otherNew = $at('03', 'andere-reis-nieuw', $this->japan);
    $usedNew = $at('04', 'reis-gebruikt-nieuw', $this->italy);
    $looseNew = $at('05', 'los-nieuw');
    $freeNew = $at('06', 'reis-ongebruikt-nieuw', $this->italy);
    tripBlog($this->italy, $usedOld, $usedNew);

    // The blog's trip: only its photos, unused first
    expect(collect(tripPanel($this->italy)->get('files'))->pluck('name')->all())
        ->toBe(['reis-ongebruikt-nieuw', 'reis-ongebruikt-oud', 'reis-gebruikt-nieuw', 'reis-gebruikt-oud']);

    // All photos: the blog's trip still on top, then the others, newest first
    expect(collect(tripPanel($this->italy)->set('panelData.trip_id', null)->get('files'))->pluck('name')->all())
        ->toBe(['reis-ongebruikt-nieuw', 'reis-ongebruikt-oud', 'reis-gebruikt-nieuw', 'reis-gebruikt-oud', 'los-nieuw', 'andere-reis-nieuw']);

    // Another trip chosen: that trip's photos
    expect(collect(tripPanel($this->italy)->set('panelData.trip_id', $this->japan->id)->get('files'))->pluck('name')->all())
        ->toBe(['andere-reis-nieuw']);
});
