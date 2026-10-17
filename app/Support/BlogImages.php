<?php

namespace App\Support;

use Awcodes\Curator\Facades\Glide;
use Awcodes\Curator\Models\Media;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Turns images from the media library (Curator) and older uploads (plain paths on the
 * public disk) into resized WebP URLs, so big phone photos never reach visitors as-is.
 */
class BlogImages
{
    public const WIDTH_CONTENT = 1600;

    public const WIDTH_THUMB = 600;

    // The sizes a browser can choose from: a phone takes 800, a laptop with a sharp screen 1600
    public const WIDTHS = [800, 1200, 1600];

    public static function url(string $path, int $width = self::WIDTH_CONTENT, string $format = 'webp'): string
    {
        return Glide::getUrl(ltrim($path, '/'), ['w' => $width, 'fm' => $format, 'q' => 80]);
    }

    /**
     * A photo at (at most) this width. When the stored file is already that small, it is served as is:
     * it was compressed on upload (ImageOptimizer), and compressing it again only makes it bigger.
     */
    public static function mediaUrl(Media $media, int $width = self::WIDTH_CONTENT): string
    {
        if ($media->type === 'image/webp' && $media->width && $media->width <= $width) {
            return Storage::disk($media->disk)->url($media->path);
        }

        return static::url($media->path, $width);
    }

    /**
     * The srcset of a photo: each width up to the photo's own, so the browser downloads what the screen needs.
     */
    public static function srcset(Media $media, array $widths = self::WIDTHS): string
    {
        $own = $media->width ?: max($widths);

        return collect($widths)
            ->map(fn (int $width) => min($width, $own))
            ->unique()
            ->map(fn (int $width) => static::mediaUrl($media, $width) . " {$width}w")
            ->join(', ');
    }

    /**
     * Normalises a CuratorPicker state (ids, media arrays or a mix) into Media models, in the chosen order.
     *
     * @return Collection<int, Media>
     */
    public static function media(mixed $state): Collection
    {
        $ids = collect(is_array($state) ? $state : [$state])
            ->map(fn ($item) => is_array($item) ? ($item['id'] ?? null) : $item)
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $media = Media::whereIn('id', $ids)->get()->keyBy('id');

        return $ids->map(fn ($id) => $media->get($id))->filter()->values();
    }

    /**
     * Images for a gallery, from the media library.
     *
     * @return Collection<int, array{src: string, thumb: string, srcset: string, full: string, alt: string, landscape: bool, ratio: float}>
     */
    public static function gallery(array $config): Collection
    {
        return static::media($config['media'] ?? [])->map(fn (Media $media) => [
            'src' => static::mediaUrl($media),
            'thumb' => static::mediaUrl($media, static::WIDTH_THUMB),
            'srcset' => static::srcset($media, [400, ...static::WIDTHS]),
            'full' => static::mediaUrl($media, ImageOptimizer::MAX_EDGE),
            'alt' => $media->alt ?: ($media->title ?: ''),
            'landscape' => ($media->width ?? 0) >= ($media->height ?? 0),
            'ratio' => $media->width && $media->height ? round($media->width / $media->height, 4) : 1.5,
        ])->values();
    }
}
