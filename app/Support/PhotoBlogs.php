<?php

namespace App\Support;

use App\Models\Blog;

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
