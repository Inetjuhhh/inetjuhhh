<?php

namespace App\Support;

use App\Models\Blog;
use Illuminate\Support\Collection;

/**
 * The pins for the travel map: every published blog that has a place or a country.
 */
class TravelMap
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function markers(): Collection
    {
        return Blog::published()
            ->newestFirst()
            ->with(['countries', 'categories', 'featuredImage', 'media'])
            ->get()
            ->map(function (Blog $blog) {
                $position = $blog->mapPosition();

                if (! $position) {
                    return null;
                }

                return [
                    'id' => $blog->id,
                    'title' => $blog->title,
                    'url' => route('blogs.show', $blog),
                    'lat' => round($position[0], 5),
                    'lng' => round($position[1], 5),
                    'place' => $blog->location_name ?: $blog->countries->pluck('name')->join(', '),
                    'country' => $blog->countries->pluck('name')->join(', '),
                    'category' => $blog->categories->pluck('name')->join(' · '),
                    'date' => $blog->publishedDate()->translatedFormat('d F Y'),
                    'excerpt' => $blog->excerpt !== 'No excerpt' ? str($blog->excerpt)->limit(120)->toString() : '',
                    'cover' => $blog->coverUrl(400, 260),
                ];
            })
            ->filter()
            ->values();
    }
}
