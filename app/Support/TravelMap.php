<?php

namespace App\Support;

use App\Models\Blog;
use App\Models\Trip;
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
            ->with(['countries', 'categories', 'trip', 'featuredImage', 'media'])
            ->get()
            ->map(fn (Blog $blog) => static::marker($blog))
            ->filter()
            ->values();
    }

    /**
     * The published stops of one trip in reading order, for the map under a story.
     * Numbered like the trip strip, so a gap appears when a part has no place.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function forTrip(Trip $trip, Blog $current): Collection
    {
        $parts = $trip->parts()->load(['countries', 'categories', 'featuredImage', 'media']);

        return $parts
            ->map(function (Blog $blog, int $i) use ($trip, $current) {
                $marker = static::marker($blog->setRelation('trip', $trip));

                return $marker ? $marker + ['number' => $i + 1, 'current' => $blog->is($current)] : null;
            })
            ->filter()
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function marker(Blog $blog): ?array
    {
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
            'place' => collect($blog->placeTags())->pluck('label')->join(' · '),
            'country' => $blog->countries->pluck('name')->join(', '),
            'category' => $blog->categories->pluck('name')->join(' · '),
            'date' => $blog->publishedDate()->translatedFormat('d F Y'),
            'excerpt' => $blog->excerpt !== 'No excerpt' ? str($blog->excerpt)->limit(120)->toString() : '',
            'cover' => $blog->coverUrl(400, 260),
            'trip' => $blog->trip ? ['slug' => $blog->trip->slug, 'name' => $blog->trip->name] : null,
            // For drawing a trip's route in the order it was written
            'time' => $blog->publishedDate()->getTimestamp(),
        ];
    }
}
