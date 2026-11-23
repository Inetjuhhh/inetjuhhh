<?php

use App\Livewire\CuratorPanel;
use App\Models\User;
use App\Support\PhotoFingerprint;
use Awcodes\Curator\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
| Noticing a photo that is already in the media library when it is uploaded again.
*/

// A made-up "photo": coloured blocks from a seed, so the same seed is the same picture
function fakePhoto(int $seed, int $width = 1200, int $height = 900, int $quality = 90): string
{
    mt_srand($seed);
    $image = imagecreatetruecolor(1200, 900);
    for ($i = 0; $i < 40; $i++) {
        $color = imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
        $x = mt_rand(0, 1200); $y = mt_rand(0, 900);
        imagefilledrectangle($image, $x, $y, $x + mt_rand(50, 500), $y + mt_rand(50, 400), $color);
    }
    ob_start();
    imagejpeg(imagescale($image, $width, $height), null, $quality);

    return ob_get_clean();
}

function libraryCopy(string $contents, string $title): Media
{
    Storage::disk('public')->put("{$title}.jpg", $contents);
    [$width, $height] = getimagesizefromstring($contents);

    return Media::create(['disk' => 'public', 'visibility' => 'public', 'name' => $title, 'path' => "{$title}.jpg",
        'width' => $width, 'height' => $height, 'size' => strlen($contents), 'type' => 'image/jpeg', 'ext' => 'jpg', 'title' => $title]);
}

function mediaPanel()
{
    return Livewire::test(CuratorPanel::class, ['settings' => [
        'diskName' => 'public', 'visibility' => 'public', 'isMultiple' => true,
        'acceptedFileTypes' => ['image/jpeg', 'image/png', 'image/webp'],
    ]]);
}

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());
});

it('gives a stored photo a fingerprint', function () {
    expect(libraryCopy(fakePhoto(1), 'zee')->fingerprint)->toMatch('/^[0-9a-f]{16}$/');
});

it('recognises the same photo, also smaller and compressed again', function () {
    $original = libraryCopy(fakePhoto(1), 'zee');
    libraryCopy(fakePhoto(2), 'berg');

    expect(PhotoFingerprint::matches(fakePhoto(1, 800, 600, 60))->pluck('id')->all())->toBe([$original->id])
        ->and(PhotoFingerprint::matches(fakePhoto(3)))->toBeEmpty();
});

it('numbers a name that is already taken', function () {
    libraryCopy(fakePhoto(1), 'zee');

    expect(CuratorPanel::uniqueTitle('berg'))->toBe('berg')
        ->and(CuratorPanel::uniqueTitle('zee'))->toBe('zee (1)');
});

it('asks before adding a photo that is already in the library', function () {
    libraryCopy(fakePhoto(1), 'zee');

    mediaPanel()
        ->set('panelData.files_to_add', [UploadedFile::fake()->createWithContent('zee.jpg', fakePhoto(1, 800, 600))])
        ->mountAction('addFiles')
        ->assertActionMounted('addFiles')
        ->assertMountedActionModalSee(['Deze foto staat al in je bibliotheek', 'zee (1)']);

    expect(Media::count())->toBe(1);
});

it('adds the photo anyway under its own name', function () {
    libraryCopy(fakePhoto(1), 'zee');

    mediaPanel()
        ->set('panelData.files_to_add', [UploadedFile::fake()->createWithContent('zee.jpg', fakePhoto(1, 800, 600))])
        ->callAction('addFiles');

    expect(Media::count())->toBe(2)->and(Media::latest('id')->first()->title)->toBe('zee (1)');
});

it('uses the photo that is already there instead of adding a copy', function () {
    $existing = libraryCopy(fakePhoto(1), 'zee');

    $panel = mediaPanel()
        ->set('panelData.files_to_add', [
            UploadedFile::fake()->createWithContent('zee.jpg', fakePhoto(1, 800, 600)),
            UploadedFile::fake()->createWithContent('berg.jpg', fakePhoto(2)),
        ])
        ->callAction('addFiles', arguments: ['useExisting' => true]);

    // The new photo is added, the known one is not; both are chosen
    expect(Media::count())->toBe(2)
        ->and(Media::where('title', 'berg')->exists())->toBeTrue()
        ->and(collect($panel->get('selected'))->pluck('id'))->toContain($existing->id);
});

it('adds a new photo without asking', function () {
    libraryCopy(fakePhoto(1), 'zee');

    mediaPanel()
        ->set('panelData.files_to_add', [UploadedFile::fake()->createWithContent('berg.jpg', fakePhoto(2))])
        // Only the click, no answer to a question: a new photo must be added by that alone
        ->mountAction('addFiles');

    expect(Media::where('title', 'berg')->exists())->toBeTrue();
});

it('compares a photo with the library while it uploads', function () {
    $existing = libraryCopy(fakePhoto(1), 'zee');

    $panel = mediaPanel()
        ->set('panelData.files_to_add', [UploadedFile::fake()->createWithContent('zee.jpg', fakePhoto(1, 800, 600))]);

    expect(array_values($panel->get('duplicateIds')))->toBe([[$existing->id]]);

    $panel->mountAction('addFiles')
        ->assertMountedActionModalSee(['Deze foto staat al in je bibliotheek', 'zee (1)']);
});

it('adds many photos a few at a time, each batch in its own request', function () {
    $photos = collect(range(1, CuratorPanel::BATCH_SIZE * 2 + 2))
        ->map(fn (int $seed) => UploadedFile::fake()->createWithContent("foto-{$seed}.jpg", fakePhoto($seed + 10, 300, 200)))
        ->all();

    $panel = mediaPanel()->set('panelData.files_to_add', $photos)->callAction('addFiles');

    // The first batch, and the browser is asked to carry on
    expect(Media::count())->toBe(CuratorPanel::BATCH_SIZE)
        ->and($panel->get('uploadQueue.keys'))->toHaveCount(CuratorPanel::BATCH_SIZE + 2);

    $panel->call('addNextUploads');
    expect(Media::count())->toBe(CuratorPanel::BATCH_SIZE * 2);

    $panel->call('addNextUploads');
    expect(Media::count())->toBe(CuratorPanel::BATCH_SIZE * 2 + 2)
        ->and($panel->get('uploadQueue'))->toBe([])
        ->and($panel->get('selected'))->toHaveCount(CuratorPanel::BATCH_SIZE * 2 + 2)
        ->and(Media::pluck('title')->sort()->values()->all())->toBe(collect(range(1, 12))->map(fn ($i) => "foto-{$i}")->sort()->values()->all());

    // Nothing left to do: another call adds nothing twice
    $panel->call('addNextUploads');
    expect(Media::count())->toBe(CuratorPanel::BATCH_SIZE * 2 + 2);
});

it('uses the existing photo and adds the rest in batches', function () {
    $existing = libraryCopy(fakePhoto(1), 'zee');
    $photos = [
        UploadedFile::fake()->createWithContent('zee.jpg', fakePhoto(1, 800, 600)),
        ...collect(range(1, CuratorPanel::BATCH_SIZE + 1))
            ->map(fn (int $seed) => UploadedFile::fake()->createWithContent("foto-{$seed}.jpg", fakePhoto($seed + 10, 300, 200))),
    ];

    $panel = mediaPanel()->set('panelData.files_to_add', $photos)
        ->callAction('addFiles', arguments: ['useExisting' => true])
        ->call('addNextUploads');

    expect(Media::count())->toBe(1 + CuratorPanel::BATCH_SIZE + 1)
        ->and(Media::where('title', 'zee (1)')->exists())->toBeFalse()
        ->and(collect($panel->get('selected'))->pluck('id'))->toContain($existing->id)
        ->and($panel->get('selected'))->toHaveCount(CuratorPanel::BATCH_SIZE + 2);
});
