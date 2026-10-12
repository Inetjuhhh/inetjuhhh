<?php

use App\Support\ImageOptimizer;
use Awcodes\Curator\Models\Media;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

// A photo-like JPEG that is far over the budget: a gradient with grain, saved at top quality
function bigPhoto(int $width = 3000, int $height = 2000): string
{
    static $made = [];

    return $made["{$width}x{$height}"] ??= makePhoto($width, $height);
}

function makePhoto(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    mt_srand(42);

    for ($y = 0; $y < $height; $y += 2) {
        for ($x = 0; $x < $width; $x += 2) {
            $grain = mt_rand(-40, 40);
            $color = imagecolorallocate(
                $image,
                max(0, min(255, (int) (255 * $x / $width) + $grain)),
                max(0, min(255, (int) (255 * $y / $height) + $grain)),
                max(0, min(255, 120 + $grain)),
            );
            imagefilledrectangle($image, $x, $y, $x + 1, $y + 1, $color);
        }
    }

    ob_start();
    imagejpeg($image, null, 98);

    return ob_get_clean();
}

function upload(string $contents, string $name, string $ext, string $type, ?string $directory = null): Media
{
    $path = ltrim("{$directory}/{$name}.{$ext}", '/');
    Storage::disk('public')->put($path, $contents);
    [$width, $height] = getimagesizefromstring($contents) ?: [null, null];

    return Media::create([
        'disk' => 'public', 'directory' => $directory, 'visibility' => 'public', 'name' => $name,
        'path' => $path, 'width' => $width, 'height' => $height, 'size' => strlen($contents),
        'type' => $type, 'ext' => $ext,
    ]);
}

it('shrinks a photo in a story to at most 250 KB', function () {
    $photo = bigPhoto();
    expect(strlen($photo))->toBeGreaterThan(ImageOptimizer::MAX_BYTES);

    $media = upload($photo, 'kathmandu', 'jpg', 'image/jpeg');

    expect($media->path)->toBe('kathmandu.webp')
        ->and($media->type)->toBe('image/webp')
        ->and($media->size)->toBeLessThanOrEqual(ImageOptimizer::MAX_BYTES)
        ->and(max($media->width, $media->height))->toBeLessThanOrEqual(ImageOptimizer::MAX_EDGE);

    Storage::disk('public')->assertExists('kathmandu.webp')->assertMissing('kathmandu.jpg');
    expect(Storage::disk('public')->size('kathmandu.webp'))->toBe($media->size);
});

it('lets the featured photo be bigger', function () {
    $story = upload(bigPhoto(), 'pokhara', 'jpg', 'image/jpeg');
    $featured = upload(bigPhoto(), 'pokhara', 'jpg', 'image/jpeg', ImageOptimizer::FEATURED_DIRECTORY);

    expect($featured->path)->toBe('uitgelicht/pokhara.webp')
        ->and($featured->size)->toBeLessThanOrEqual(ImageOptimizer::FEATURED_MAX_BYTES)
        ->and($featured->width)->toBeGreaterThan($story->width);
});

it('leaves photos that are already small alone', function () {
    $small = bigPhoto(400, 300);
    $media = upload($small, 'klein', 'jpg', 'image/jpeg');

    expect($media->path)->toBe('klein.jpg')->and($media->size)->toBe(strlen($small));
});

it('leaves gifs alone, because they can be animated', function () {
    $media = upload(str_repeat('x', 300 * 1024), 'animatie', 'gif', 'image/gif');

    expect($media->path)->toBe('animatie.gif');
});
