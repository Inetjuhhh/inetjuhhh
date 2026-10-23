<?php

use App\Models\Blog;
use App\Models\Country;
use App\Models\Trip;
use App\Models\User;
use Awcodes\Curator\Models\Media;
use Awcodes\Curator\Resources\Media\Pages\ListMedia;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function mediaLibraryPhoto(string $name, ?string $alt = null): Media
{
    Storage::disk('public')->put("media/{$name}.jpg", 'x');

    return Media::create(['disk' => 'public', 'directory' => 'media', 'name' => $name, 'path' => "media/{$name}.jpg",
        'type' => 'image/jpeg', 'ext' => 'jpg', 'size' => 1, 'width' => 1600, 'height' => 1200, 'visibility' => 'public', 'alt' => $alt]);
}

function mediaLibraryBlog(string $title, array $attributes, Media ...$photos): Blog
{
    return Blog::create($attributes + [
        'title' => $title, 'slug' => str($title)->slug(), 'excerpt' => '', 'status' => 'published', 'published_at' => '2026-07-30',
        'placed_by_id' => User::first()->id,
        'content' => ['type' => 'doc', 'content' => array_map(
            fn (Media $photo) => ['type' => 'customBlock', 'attrs' => ['id' => 'photo', 'config' => ['media' => $photo->id]]],
            $photos,
        )],
    ]);
}

it('remembers which photos are in a blog, also after changing the story', function () {
    [$cover, $first, $second] = [mediaLibraryPhoto('cover'), mediaLibraryPhoto('eerste'), mediaLibraryPhoto('tweede')];
    $blog = mediaLibraryBlog('Toscaanse hemel', ['featured_image_id' => $cover->id], $first);

    expect($blog->media()->pluck('id')->sort()->values()->all())->toBe([$cover->id, $first->id]);

    $blog->update(['content' => ['type' => 'doc', 'content' => [
        ['type' => 'customBlock', 'attrs' => ['id' => 'gallery', 'config' => ['media' => [$second->id]]]],
    ]]]);

    expect($blog->media()->pluck('id')->sort()->values()->all())->toBe([$cover->id, $second->id])
        ->and($first->blogs()->count())->toBe(0);
});

it('filters the library on trip, country, year and photos that are used nowhere', function () {
    $italy = Country::create(['name' => 'Italië']);
    $trip = Trip::create(['name' => 'Italië 2026', 'starts_on' => '2026-07-20', 'ends_on' => '2026-08-10']);
    [$tuscany, $home, $loose, $cover] = [mediaLibraryPhoto('toscane'), mediaLibraryPhoto('thuis'), mediaLibraryPhoto('los'), mediaLibraryPhoto('omslag')];
    $trip->update(['cover_image_id' => $cover->id]);

    mediaLibraryBlog('Toscaanse hemel', ['trip_id' => $trip->id, 'location_name' => 'Toscane, Italië'], $tuscany)->countries()->attach($italy);
    mediaLibraryBlog('Thuis', ['published_at' => '2024-03-01'], $home);

    Livewire::test(ListMedia::class)
        ->assertCanSeeTableRecords([$tuscany, $home, $loose])
        ->filterTable('trip', $trip->id)->assertCanSeeTableRecords([$tuscany])->assertCanNotSeeTableRecords([$home, $loose])
        ->removeTableFilters()->filterTable('country', $italy->id)->assertCanSeeTableRecords([$tuscany])->assertCanNotSeeTableRecords([$home, $loose])
        ->removeTableFilters()->filterTable('year', 2024)->assertCanSeeTableRecords([$home])->assertCanNotSeeTableRecords([$tuscany, $loose])
        ->removeTableFilters()->filterTable('unused')->assertCanSeeTableRecords([$loose])->assertCanNotSeeTableRecords([$tuscany, $home, $cover]);
});

it('finds photos by their description and by the title and place of their blog, every word counting', function () {
    [$pizza, $tuscany, $other] = [mediaLibraryPhoto('IMG_1', 'Sam eet pizza in Napels'), mediaLibraryPhoto('IMG_2'), mediaLibraryPhoto('IMG_3', 'Een pizza thuis')];
    mediaLibraryBlog('Toscaanse hemel', ['location_name' => 'Toscane, Italië'], $tuscany);
    mediaLibraryBlog('Thuis', [], $other);

    Livewire::test(ListMedia::class)
        ->searchTable('Toscane')->assertCanSeeTableRecords([$tuscany])->assertCanNotSeeTableRecords([$pizza, $other])
        ->searchTable('sam pizza')->assertCanSeeTableRecords([$pizza])->assertCanNotSeeTableRecords([$tuscany, $other]);
});

it('shows 48 photos a page, each with its place and trip', function () {
    $trip = Trip::create(['name' => 'Italië 2026', 'starts_on' => '2026-07-20', 'ends_on' => '2026-08-10']);
    $photo = mediaLibraryPhoto('toscane');
    mediaLibraryBlog('Toscaanse hemel', ['trip_id' => $trip->id, 'location_name' => 'Toscane, Italië'], $photo);

    Livewire::test(ListMedia::class)
        ->assertSet('tableRecordsPerPage', 48)
        ->assertSee('📍 Italië – Toscane')
        ->assertSee('🧭 Italië 2026');
});

it('says where a photo outside the blogs is used: a trip cover, a page or the avatar', function () {
    [$cover, $loose] = [mediaLibraryPhoto('omslag'), mediaLibraryPhoto('los')];
    Trip::create(['name' => 'Costa Rica 2027', 'starts_on' => '2027-03-01', 'ends_on' => '2027-03-20', 'cover_image_id' => $cover->id]);

    Livewire::test(ListMedia::class)
        ->assertSee('Omslag van Costa Rica 2027')
        ->assertSee('Nergens gebruikt');
});
