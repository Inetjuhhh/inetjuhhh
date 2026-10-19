<?php

use App\Filament\Pages\EditSiteSettings;
use App\Models\User;
use App\Support\Avatar;
use Awcodes\Curator\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());
});

// The photo as the media picker holds a choice: its details, under a random key
function chosen(Media $media): array
{
    return [(string) Str::uuid() => $media->toArray()];
}

function photoForAvatar(int $width = 800, int $height = 600, string $path = 'media/ik.jpg'): Media
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 200, 120, 80));
    ob_start();
    imagejpeg($image);
    Storage::disk('public')->put($path, ob_get_clean());

    return Media::create([
        'disk' => 'public', 'directory' => 'media', 'name' => 'ik', 'path' => $path, 'type' => 'image/jpeg',
        'ext' => 'jpg', 'size' => 1000, 'width' => $width, 'height' => $height, 'visibility' => 'public',
    ]);
}

it('uses the avatar that came with the site until another is chosen', function () {
    expect(Avatar::faviconUrl())->toBe(asset('favicon.ico'))
        ->and(Avatar::url(180))->toBe(asset('apple-touch-icon.png'));

    $this->get('/')->assertSee('href="' . asset('favicon.ico') . '"', false);
});

it('makes square icons from the chosen photo and shows them everywhere', function () {
    $media = photoForAvatar(800, 600);

    Livewire::test(EditSiteSettings::class)
        ->set('data.avatar', chosen($media))
        ->call('save')
        ->assertNotified('Je nieuwe avatar staat erop');

    foreach (Avatar::SIZES as $size) {
        $file = str_replace(asset('storage') . '/', '', Avatar::url($size));
        Storage::disk('public')->assertExists($file);
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($file));
        expect([$width, $height])->toBe([$size, $size]);
    }

    $html = $this->get('/')->assertOk()->getContent();
    expect($html)->toContain('rel="icon" href="' . Avatar::url(32) . '"')   // the tab
        ->toContain('rel="apple-touch-icon" href="' . Avatar::url(180) . '"')   // home screen
        ->toContain('src="' . Avatar::url(512) . '"');   // menu and footer
    expect(filament()->getPanel('admin')->getFavicon())->toBe(Avatar::url(32));
});

it('gives a new avatar a new name, so browsers fetch it, and removes the old files', function () {
    Livewire::test(EditSiteSettings::class)->set('data.avatar', chosen(photoForAvatar()))->call('save');
    $first = Avatar::url(32);

    Livewire::test(EditSiteSettings::class)->set('data.avatar', chosen(photoForAvatar(path: 'media/nieuw.jpg')))->call('save');

    expect(Avatar::url(32))->not->toBe($first);
    Storage::disk('public')->assertMissing(str_replace(asset('storage') . '/', '', $first));
});

it('puts the original avatar back when the choice is emptied', function () {
    Livewire::test(EditSiteSettings::class)->set('data.avatar', chosen(photoForAvatar()))->call('save');

    Livewire::test(EditSiteSettings::class)
        ->set('data.avatar', [])
        ->call('save')
        ->assertNotified('De oorspronkelijke avatar staat weer terug');

    expect(Avatar::faviconUrl())->toBe(asset('favicon.ico'));
    expect(Storage::disk('public')->allFiles('avatar'))->toBeEmpty();
});

it('says so when the file is not a photo it can use', function () {
    // A damaged file that only pretends to be a JPG (SVG and other types the picker refuses by itself)
    Storage::disk('public')->put('media/kapot.jpg', 'dit is geen foto');
    $broken = Media::create(['disk' => 'public', 'directory' => 'media', 'name' => 'kapot', 'path' => 'media/kapot.jpg',
        'type' => 'image/jpeg', 'ext' => 'jpg', 'size' => 16, 'width' => 100, 'height' => 100, 'visibility' => 'public']);

    Livewire::test(EditSiteSettings::class)->set('data.avatar', chosen($broken))->call('save')->assertNotified('Niet gelukt');

    expect(Avatar::mediaId())->toBeNull();
});

it('makes the tab icon round, with see-through corners, and keeps the home screen icon square', function () {
    Livewire::test(EditSiteSettings::class)->set('data.avatar', chosen(photoForAvatar()))->call('save');

    $read = fn (int $size) => imagecreatefromstring(Storage::disk('public')->get(str_replace(asset('storage') . '/', '', Avatar::url($size))));
    $alpha = fn ($image, int $x, int $y) => imagecolorsforindex($image, imagecolorat($image, $x, $y))['alpha'];

    $tab = $read(32);
    expect($alpha($tab, 0, 0))->toBe(127)          // corner: see-through
        ->and($alpha($tab, 16, 16))->toBe(0);        // middle: solid

    $homeScreen = $read(180);
    expect($alpha($homeScreen, 0, 0))->toBe(0);      // phones round it themselves
});

it('makes the icons anew when the same photo is saved again', function () {
    $media = photoForAvatar();
    Livewire::test(EditSiteSettings::class)->set('data.avatar', chosen($media))->call('save');
    $before = Avatar::url(32);

    Livewire::test(EditSiteSettings::class)->set('data.avatar', chosen($media))->call('save')->assertNotified('Je nieuwe avatar staat erop');

    expect(Avatar::url(32))->not->toBe($before);
});

it('has a round default tab icon', function () {
    $icon = imagecreatefromstring(file_get_contents(public_path('favicon-32.png')));

    expect(imagecolorsforindex($icon, imagecolorat($icon, 0, 0))['alpha'])->toBe(127);
});
