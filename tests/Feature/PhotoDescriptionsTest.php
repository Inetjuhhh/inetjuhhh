<?php

use App\Filament\Curator\EditMedia;
use App\Models\Blog;
use App\Models\User;
use App\Support\AltTextWriter;
use App\Support\PhotoBlogs;
use Awcodes\Curator\Models\Media;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => Storage::fake('public'));

function libraryPhotoForAlt(string $name, ?string $alt = null): Media
{
    Storage::disk('public')->put("media/{$name}.jpg", 'x');

    return Media::create(['disk' => 'public', 'directory' => 'media', 'name' => $name, 'path' => "media/{$name}.jpg",
        'type' => 'image/jpeg', 'ext' => 'jpg', 'size' => 1, 'width' => 1600, 'height' => 1200, 'visibility' => 'public', 'alt' => $alt]);
}

it('knows which blog a photo is in: as featured photo, in a photo block or in a gallery', function () {
    [$featured, $single, $inGallery, $loose] = [libraryPhotoForAlt('a'), libraryPhotoForAlt('b'), libraryPhotoForAlt('c'), libraryPhotoForAlt('d')];
    $blog = Blog::create([
        'title' => 'Kiezels in Pompeï', 'slug' => 'pompei', 'excerpt' => '', 'status' => 'published',
        'placed_by_id' => User::factory()->create()->id, 'featured_image_id' => $featured->id,
        'content' => ['type' => 'doc', 'content' => [
            ['type' => 'customBlock', 'attrs' => ['id' => 'photo', 'config' => ['media' => $single->id]]],
            ['type' => 'customBlock', 'attrs' => ['id' => 'gallery', 'config' => ['media' => [$inGallery->id]]]],
        ]],
    ]);

    $index = PhotoBlogs::index();

    expect($index[$featured->id]->is($blog))->toBeTrue()
        ->and($index[$single->id]->is($blog))->toBeTrue()
        ->and($index[$inGallery->id]->is($blog))->toBeTrue()
        ->and($index)->not->toHaveKey($loose->id);
});

it('fills in the suggested description in the admin, without saving it yet', function () {
    $this->actingAs(User::factory()->create());
    $photo = libraryPhotoForAlt('vesuvius');
    $this->mock(AltTextWriter::class)->shouldReceive('describe')->once()->andReturn('Drie mensen bij de rand van een vulkaankrater');

    Livewire::test(EditMedia::class, ['record' => $photo->getRouteKey()])
        ->callAction('describe')
        ->assertNotified('Beschrijving voorgesteld')
        ->assertSet('data.alt', 'Drie mensen bij de rand van een vulkaankrater');

    expect($photo->fresh()->alt)->toBeNull();
});

it('describes the photos without a description one by one, and leaves the others alone', function () {
    config(['services.anthropic.key' => 'test']);
    $empty = libraryPhotoForAlt('leeg');
    $written = libraryPhotoForAlt('al-beschreven', 'Zelf geschreven');
    $this->mock(AltTextWriter::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldReceive('describe')->once()->andReturn('Een jongetje op een strand');
    });

    $this->artisan('media:describe', ['--limit' => 10])->expectsConfirmation('Starten?', 'yes')->assertSuccessful();

    expect($empty->fresh()->alt)->toBe('Een jongetje op een strand')
        ->and($written->fresh()->alt)->toBe('Zelf geschreven');
});

it('only counts and estimates with --dry-run, sending nothing', function () {
    libraryPhotoForAlt('een');
    libraryPhotoForAlt('twee');
    $this->mock(AltTextWriter::class)->shouldNotReceive('describe');

    $this->artisan('media:describe', ['--dry-run' => true])
        ->expectsOutputToContain("Foto's zonder beschrijving: 2")
        ->expectsOutputToContain('miljoen tokens')
        ->assertSuccessful();
});

it('uses the cheaper model for photo descriptions, separate from the tag suggestions', function () {
    expect(config('services.anthropic.alt_model'))->toBe('claude-haiku-5-5')
        ->and(config('services.anthropic.model'))->toBe('claude-opus-5-5');
});

it('shortens a long description after a whole word or a comma, never with a half word', function () {
    $long = 'Een man, een vrouw met strohoed en een jongetje met bril en gele zonnehoed lachen in de camera op de rand van een vulkaankrater bij Pompeï, met rotswanden op de achtergrond';

    expect(AltTextWriter::shorten($long))->toBe('Een man, een vrouw met strohoed en een jongetje met bril en gele zonnehoed lachen in de camera op de rand van een vulkaankrater bij Pompeï.')
        ->and(AltTextWriter::shorten('Een jongetje op het strand.'))->toBe('Een jongetje op het strand.');
});
