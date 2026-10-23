<?php

namespace App\Support;

use App\Models\Blog;
use Awcodes\Curator\Models\Media;

/**
 * Which blog each photo of the media library is in: as featured photo, or in a photo or gallery
 * block of the story (config.media). Used to tell Claude where a photo was taken (AltTextWriter).
 */
class PhotoBlogs
{
    /**
     * @return array<int, Blog> media id → the (first) blog it is in
     */
    public static function index(): array
    {
        $index = [];

        foreach (Blog::with('countries')->get(['id', 'title', 'location_name', 'featured_image_id', 'content']) as $blog) {
            foreach ([$blog->featured_image_id, ...static::idsIn($blog->content['content'] ?? [])] as $id) {
                if ($id) {
                    $index[(int) $id] ??= $blog;
                }
            }
        }

        return $index;
    }

    /**
     * Stores which photos are in this blog (the blog_media table, for the media library's filters).
     */
    public static function sync(Blog $blog): void
    {
        $ids = collect([$blog->featured_image_id, ...static::idsInDoc($blog->content)])->filter()->unique();

        // A photo deleted from the library can still be named in an older story
        $existing = Media::whereIn('id', $ids)->pluck('id');

        $blog->media()->sync($existing);
    }

    public static function syncAll(): void
    {
        Blog::query()->select(['id', 'featured_image_id', 'content'])->each(fn (Blog $blog) => static::sync($blog));
    }

    /**
     * The photos in the photo and gallery blocks of a story or page.
     *
     * @return list<int>
     */
    public static function idsInDoc(?array $doc): array
    {
        return collect(static::idsIn($doc['content'] ?? []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<int|string>
     */
    private static function idsIn(array $nodes): array
    {
        $ids = [];

        foreach ($nodes as $node) {
            $media = $node['attrs']['config']['media'] ?? null;
            if ($media !== null) {
                array_push($ids, ...(array) $media);
            }

            if (! empty($node['content']) && is_array($node['content'])) {
                array_push($ids, ...static::idsIn($node['content']));
            }
        }

        return $ids;
    }
}
