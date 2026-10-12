<?php

namespace App\Support;

use Awcodes\Curator\Models\Media;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Shrinks uploaded photos to a file size budget before they are stored, so holiday photos
 * don't fill up the server. Photos are saved as WebP: the same sharpness as JPEG at roughly
 * two thirds of the size. Visitors still get resized copies from Glide (see BlogImages).
 */
class ImageOptimizer
{
    // Photos in a story: sharp on a laptop, also full screen in the lightbox
    public const MAX_BYTES = 250 * 1024;

    public const MAX_EDGE = 2048;

    // The featured photo fills the whole screen at the top of the story, so it may be bigger
    public const FEATURED_DIRECTORY = 'uitgelicht';

    public const FEATURED_MAX_BYTES = 500 * 1024;

    public const FEATURED_MAX_EDGE = 2400;

    // Quality steps tried from best to smallest; below this, shrinking the photo looks better
    private const QUALITIES = [82, 76, 70, 64, 58];

    private const OPTIMIZABLE = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Optimises the file of a new (or swapped) media item in place and updates its details.
     * GIFs (maybe animated) and SVGs are left alone; on any failure the original is kept.
     */
    public static function optimizeMedia(Media $media): void
    {
        if (! in_array($media->type, self::OPTIMIZABLE, true) || blank($media->path)) {
            return;
        }

        $featured = $media->directory === self::FEATURED_DIRECTORY;
        $maxBytes = $featured ? self::FEATURED_MAX_BYTES : self::MAX_BYTES;
        $maxEdge = $featured ? self::FEATURED_MAX_EDGE : self::MAX_EDGE;

        $disk = Storage::disk($media->disk);

        try {
            $original = $disk->get($media->path);

            // Already small enough: don't recompress (that only costs sharpness)
            if (strlen($original) <= $maxBytes && max($media->width, $media->height) <= $maxEdge) {
                return;
            }

            $result = static::shrink($original, $maxBytes, $maxEdge);
        } catch (Throwable $e) {
            Log::warning("Could not optimise image {$media->path}: {$e->getMessage()}");

            return;
        }

        $path = ltrim(($media->directory ? "{$media->directory}/" : '') . "{$media->name}.webp", '/');

        if ($path !== $media->path && $disk->exists($path)) {
            $media->name .= '-' . time();
            $path = ltrim(($media->directory ? "{$media->directory}/" : '') . "{$media->name}.webp", '/');
        }

        $disk->put($path, $result['data'], $media->visibility);

        if ($path !== $media->path) {
            $disk->delete($media->path);
        }

        $media->fill([
            'path' => $path,
            'ext' => 'webp',
            'type' => 'image/webp',
            'size' => strlen($result['data']),
            'width' => $result['width'],
            'height' => $result['height'],
        ]);
    }

    /**
     * @return array{data: string, width: int, height: int}
     */
    public static function shrink(string $contents, int $maxBytes = self::MAX_BYTES, int $maxEdge = self::MAX_EDGE): array
    {
        // A 12 megapixel phone photo needs about 60 MB while decoding
        $memory = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            // Also turns photos upright according to their EXIF data, and drops that data (with GPS) from the file
            $image = (new ImageManager(new Driver()))->read($contents)->scaleDown($maxEdge, $maxEdge);

            while (true) {
                foreach (self::QUALITIES as $quality) {
                    $data = $image->toWebp($quality)->toString();

                    if (strlen($data) <= $maxBytes) {
                        break 2;
                    }
                }

                // Still too big at the lowest quality: make the photo 15% smaller and try again
                if (max($image->width(), $image->height()) <= 800) {
                    break;
                }

                $image->scale((int) round($image->width() * 0.85));
            }

            return ['data' => $data, 'width' => $image->width(), 'height' => $image->height()];
        } finally {
            ini_set('memory_limit', $memory);
        }
    }
}
