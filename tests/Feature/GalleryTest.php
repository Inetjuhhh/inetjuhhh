<?php

use App\Filament\Forms\InsertPhotosAction;
use App\Filament\Resources\BlogResource\Pages\EditBlog;
use App\Models\Blog;
use App\Models\User;
use App\Support\GalleryLayout;
use Awcodes\Curator\Models\Media;
use Livewire\Livewire;

/*
| Photo galleries: the photo button in the editor, and the mixed rows on the website.
*/

function shaped(string $name, float $ratio): array
{
    return ['name' => $name, 'ratio' => $ratio];
}

function names(array $rows): array
{
    return array_map(fn (array $row) => implode(' ', array_column($row, 'name')), $rows);
}

it('puts a landscape next to its share of the portraits in every row', function () {
    $photos = [shaped('L1', 2.1), shaped('L2', 1.5), shaped('P1', 0.67), shaped('P2', 0.75), shaped('L3', 1.33), shaped('P3', 0.67), shaped('P4', 0.8)];

    // 4 portraits over 3 landscapes: 2, 1, 1; every other row mirrored
    expect(names(GalleryLayout::rows($photos, 2)))->toBe(['L1 P1 P2', 'P3 L2', 'L3 P4'])
        // On a phone at most one portrait per landscape; what is left pairs up
        ->and(names(GalleryLayout::rows($photos, 1)))->toBe(['L1 P1', 'P2 L2', 'L3 P3', 'P4']);
});

it('keeps galleries of only one shape in tidy rows', function () {
    $portraits = array_map(fn ($i) => shaped("P{$i}", 0.75), range(1, 5));
    $landscapes = array_map(fn ($i) => shaped("L{$i}", 1.5), range(1, 3));

    expect(names(GalleryLayout::rows($portraits, 2)))->toBe(['P1 P2 P3', 'P5 P4'])
        ->and(names(GalleryLayout::rows($landscapes, 2)))->toBe(['L1 L2', 'L3']);
});

function photoInLibrary(int $width, int $height): Media
{
    return Media::create(['disk' => 'public', 'visibility' => 'public', 'name' => "foto-{$width}x{$height}-" . uniqid(),
        'path' => "foto-{$width}x{$height}.webp", 'width' => $width, 'height' => $height, 'size' => 1000, 'type' => 'image/webp', 'ext' => 'webp']);
}

function blogWithGallery(array $media): Blog
{
    return Blog::create([
        'title' => 'Vík', 'slug' => 'vik', 'excerpt' => 'Intro', 'status' => 'published', 'placed_by_id' => User::first()->id,
        'content' => ['type' => 'doc', 'content' => [
            ['type' => 'customBlock', 'attrs' => ['id' => 'gallery', 'config' => ['media' => collect($media)->pluck('id')->all(), 'layout' => 'grid']]],
        ]],
    ]);
}

it('shows every photo of a gallery in its own shape', function () {
    User::factory()->create();
    $blog = blogWithGallery([photoInLibrary(2000, 1000), photoInLibrary(750, 1000), photoInLibrary(1500, 1000)]);

    $this->get(route('blogs.show', $blog))
        ->assertOk()
        ->assertSee('aspect-ratio: 2', false)
        ->assertSee('aspect-ratio: 0.75', false)
        ->assertSee('aspect-ratio: 1.5', false)
        ->assertDontSee('aspect-square', false);
});

describe('the photo button in the editor', function () {
    beforeEach(fn () => $this->photos = [photoInLibrary(2000, 1000)->id, photoInLibrary(750, 1000)->id]);

    it('is in the toolbar of the story editor', function () {
        $this->actingAs(User::factory()->create());
        $blog = Blog::create(['title' => 'Vík', 'slug' => 'vik', 'excerpt' => 'Intro', 'status' => 'draft', 'placed_by_id' => auth()->id(),
            'content' => ['type' => 'doc', 'content' => []]]);

        Livewire::test(EditBlog::class, ['record' => $blog->id])->assertSee('data-rich-editor-tool="insertPhotos"', false);
    });

    it('makes one gallery of several photos', function () {
        $blocks = InsertPhotosAction::blocks($this->photos, asGallery: true, caption: 'Bij Vík');

        expect($blocks)->toHaveCount(1)
            ->and($blocks[0]['attrs']['id'])->toBe('gallery')
            ->and($blocks[0]['attrs']['config'])->toMatchArray(['media' => $this->photos, 'caption' => 'Bij Vík'])
            ->and($blocks[0]['attrs']['label'])->toBe('Fotogalerij · 2 foto\'s · gemengde rijen');
    });

    it('puts the photos one below the other when the gallery is switched off', function () {
        $blocks = InsertPhotosAction::blocks($this->photos, asGallery: false, caption: 'Bij Vík');

        expect(collect($blocks)->pluck('attrs.id')->all())->toBe(['photo', 'photo'])
            ->and($blocks[0]['attrs']['config'])->not->toHaveKey('caption')
            ->and($blocks[1]['attrs']['config']['caption'])->toBe('Bij Vík');
    });

    it('makes one photo of one photo, also with the gallery switched on', function () {
        expect(collect(InsertPhotosAction::blocks([$this->photos[0]], asGallery: true, caption: null))->pluck('attrs.id')->all())->toBe(['photo']);
    });
});
