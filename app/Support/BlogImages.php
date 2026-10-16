<?php

namespace App\Support;

use Awcodes\Curator\Facades\Glide;
use Awcodes\Curator\Models\Media;
use Illuminate\Support\Collection;

/**
 * Turns images from the media library (Curator) and older uploads (plain paths on the
 * public disk) into resized WebP URLs, so big phone photos never reach visitors as-is.
 */
class BlogImages
{
    public const WIDTH_CONTENT = 1600;

    public const WIDTH_THUMB = 600;

    public static function url(string $path, int $width = self::WIDTH_CONTENT, string $format = 'webp'): string
    {
        return Glide::getUrl(ltrim($path, '/'), ['w' => $width, 'fm' => $format, 'q' => 80]);
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
     * @return Collection<int, array{src: string, thumb: string, full: string, alt: string, landscape: bool}>
     */
    public static function gallery(array $config): Collection
    {
        return static::media($config['media'] ?? [])->map(fn (Media $media) => [
            'src' => static::url($media->path),
            'thumb' => static::url($media->path, static::WIDTH_THUMB),
            'full' => static::url($media->path, ImageOptimizer::MAX_EDGE),
            'alt' => $media->alt ?: ($media->title ?: ''),
            'landscape' => ($media->width ?? 0) >= ($media->height ?? 0),
        ])->values();
    }
}
