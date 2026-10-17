<?php

use App\Support\BlogImages;
use Awcodes\Curator\Models\Media;

/*
| Which photo file a visitor gets: resized copies in the sizes a screen needs, or the stored file
| itself when it is already small enough (compressing it again would only make it bigger).
*/

function libraryPhoto(int $width, string $type = 'image/webp'): Media
{
    return Media::create(['disk' => 'public', 'directory' => 'wordpress/2024', 'visibility' => 'public', 'name' => "foto-{$width}",
        'path' => "wordpress/2024/foto-{$width}.webp", 'width' => $width, 'height' => (int) ($width * 0.75), 'size' => 200_000, 'type' => $type, 'ext' => 'webp']);
}

it('serves the stored photo when it is not wider than asked', function () {
    $photo = libraryPhoto(1445);

    expect(BlogImages::mediaUrl($photo, 1600))->toEndWith('/storage/wordpress/2024/foto-1445.webp')
        ->and(BlogImages::mediaUrl($photo, 800))->toContain('/curator/')->toContain('w=800');
});

it('offers phones a smaller copy and never a size bigger than the photo', function () {
    $srcset = BlogImages::srcset(libraryPhoto(1445));

    expect($srcset)->toContain('w=800')->toContain(' 800w')
        ->toContain('w=1200')->toContain(' 1200w')
        ->toContain('/storage/wordpress/2024/foto-1445.webp 1445w')
        ->not->toContain('1600w');
});

it('lets a small photo be just itself', function () {
    expect(BlogImages::srcset(libraryPhoto(700)))->toBe(url('/storage/wordpress/2024/foto-700.webp') . ' 700w');
});

it('resizes photos that were not stored as WebP, even when they are small', function () {
    expect(BlogImages::mediaUrl(libraryPhoto(700, 'image/gif'), 1600))->toContain('/curator/');
});
