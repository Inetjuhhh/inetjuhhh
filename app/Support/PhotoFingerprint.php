<?php

namespace App\Support;

use Awcodes\Curator\Models\Media;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * A fingerprint of what a photo looks like ("difference hash"): the photo is shrunk to 9×8 grey
 * pixels and each pixel is compared with its right neighbour, giving 64 yes/no answers. Resizing,
 * compressing or WebP don't change those answers, so the same photo uploaded again has (almost)
 * the same fingerprint, while a different photo differs in many of the 64.
 */
class PhotoFingerprint
{
    // At most this many of the 64 answers may differ to be looked at more closely (see looksTheSame)
    public const MAX_DISTANCE = 10;

    // The closer look: 32×32 pixels in colour may differ this much on average (0–255). Measured on the
    // library: the same photo stays below 17, different photos with a similar layout (sunsets!) start at 24
    public const MAX_PIXEL_DIFFERENCE = 20;

    public static function fromContents(string $contents): ?string
    {
        try {
            $image = @imagecreatefromstring($contents);
        } catch (Throwable) {
            return null;
        }

        if (! $image) {
            return null;
        }

        $small = imagecreatetruecolor(9, 8);
        imagecopyresampled($small, $image, 0, 0, 0, 0, 9, 8, imagesx($image), imagesy($image));
        imagefilter($small, IMG_FILTER_GRAYSCALE);

        $bits = '';
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $bits .= (imagecolorat($small, $x, $y) & 0xFF) > (imagecolorat($small, $x + 1, $y) & 0xFF) ? '1' : '0';
            }
        }

        // 64 bits as 16 hex characters, in two halves (a PHP integer can't hold 64 unsigned bits)
        return sprintf('%08x%08x', bindec(substr($bits, 0, 32)), bindec(substr($bits, 32)));
    }

    public static function forMedia(Media $media): ?string
    {
        if (! str_starts_with((string) $media->type, 'image/') || $media->type === 'image/svg+xml') {
            return null;
        }

        try {
            return static::fromContents(Storage::disk($media->disk)->get($media->path));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * How many of the 64 answers differ.
     */
    public static function distance(string $a, string $b): int
    {
        return substr_count(decbin(hexdec(substr($a, 0, 8)) ^ hexdec(substr($b, 0, 8))), '1')
            + substr_count(decbin(hexdec(substr($a, 8)) ^ hexdec(substr($b, 8))), '1');
    }

    /**
     * Photos in the library that are this photo (also resized, compressed or slightly edited), the most alike first.
     *
     * @return Collection<int, Media>
     */
    public static function matches(string $contents, array $exceptIds = []): Collection
    {
        $fingerprint = static::fromContents($contents);
        if ($fingerprint === null) {
            return collect();
        }

        // A few thousand short strings: comparing them all in PHP is quicker than anything clever
        $distances = Media::query()
            ->whereNotNull('fingerprint')
            ->whereNotIn('id', $exceptIds)
            ->pluck('fingerprint', 'id')
            ->map(fn (string $other) => static::distance($fingerprint, $other))
            ->filter(fn (int $distance) => $distance <= self::MAX_DISTANCE)
            ->sort()
            // The closer look opens each photo, so only the nearest few (a real copy is always among them)
            ->take(8);

        if ($distances->isEmpty()) {
            return collect();
        }

        $media = Media::whereIn('id', $distances->keys())->get()->keyBy('id');
        $mine = static::pixels($contents);

        return $distances->keys()
            ->map(fn ($id) => $media->get($id))
            ->filter(function (?Media $other) use ($mine) {
                try {
                    return $other && static::looksTheSame($mine, static::pixels(Storage::disk($other->disk)->get($other->path)));
                } catch (Throwable) {
                    return false;
                }
            })
            ->values();
    }

    /**
     * The closer look: the same shape, and the colours of 32×32 pixels close together.
     */
    private static function looksTheSame(?array $a, ?array $b): bool
    {
        if (! $a || ! $b || abs($a['ratio'] - $b['ratio']) / $b['ratio'] > 0.05) {
            return false;
        }

        $total = 0;
        foreach ($a['pixels'] as $i => $value) {
            $other = $b['pixels'][$i];
            $total += abs(($value >> 16 & 255) - ($other >> 16 & 255)) + abs(($value >> 8 & 255) - ($other >> 8 & 255)) + abs(($value & 255) - ($other & 255));
        }

        return $total / (count($a['pixels']) * 3) <= self::MAX_PIXEL_DIFFERENCE;
    }

    /**
     * @return array{ratio: float, pixels: list<int>}|null
     */
    private static function pixels(string $contents): ?array
    {
        $image = @imagecreatefromstring($contents);
        if (! $image) {
            return null;
        }

        $small = imagecreatetruecolor(32, 32);
        imagecopyresampled($small, $image, 0, 0, 0, 0, 32, 32, imagesx($image), imagesy($image));

        $pixels = [];
        for ($y = 0; $y < 32; $y++) {
            for ($x = 0; $x < 32; $x++) {
                $pixels[] = imagecolorat($small, $x, $y);
            }
        }

        return ['ratio' => imagesx($image) / imagesy($image), 'pixels' => $pixels];
    }
}
