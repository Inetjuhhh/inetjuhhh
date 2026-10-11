<?php

namespace App\Support;

use App\Models\Blog;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Stories to read after this one: the previous and next story from the same region
 * (falling back to the previous and next story overall), and a few more that are nearby
 * or about the same things.
 */
class RelatedBlogs
{
    // Stories closer than this count as "the same region", also across a border
    public const REGION_KM = 1500;

    public function __construct(
        public readonly ?Blog $previous,
        public readonly ?Blog $next,
        public readonly bool $previousInRegion,
        public readonly bool $nextInRegion,
        public readonly string $previousLabel,
        public readonly string $nextLabel,
        /** @var Collection<int, Blog> */
        public readonly Collection $more,
        public readonly bool $moreInRegion = false,
    ) {}

    public static function for(Blog $blog, int $moreCount = 3): self
    {
        $blog->loadMissing(['countries', 'categories', 'tags']);

        $others = Blog::published()
            ->whereKeyNot($blog->getKey())
            ->with(['countries', 'categories', 'tags', 'trip', 'featuredImage', 'media'])
            ->get();

        $date = $blog->publishedDate();
        $inTrip = $blog->trip_id ? $others->where('trip_id', $blog->trip_id) : collect();
        $inRegion = $others->filter(fn (Blog $other) => self::sameRegion($blog, $other));

        // First the trip, then the region, then simply the previous or next story in time
        $previousInTrip = self::latestBefore($inTrip, $date);
        $nextInTrip = self::earliestAfter($inTrip, $date);

        $previousInRegion = $previousInTrip ?? self::latestBefore($inRegion, $date);
        $nextInRegion = $nextInTrip ?? self::earliestAfter($inRegion, $date);

        $previous = $previousInRegion ?? self::latestBefore($others, $date);
        $next = $nextInRegion ?? self::earliestAfter($others, $date);

        $shown = collect([$previous?->getKey(), $next?->getKey()])->filter();

        $more = $others
            ->reject(fn (Blog $other) => $shown->contains($other->getKey()))
            ->map(fn (Blog $other) => ['blog' => $other, 'score' => self::score($blog, $other)])
            // Best match first; equal scores show the newest story first
            ->sortBy([
                fn ($a, $b) => $b['score'] <=> $a['score'],
                fn ($a, $b) => $b['blog']->publishedDate() <=> $a['blog']->publishedDate(),
            ])
            ->pluck('blog')
            ->take($moreCount)
            ->values();

        return new self(
            previous: $previous,
            next: $next,
            previousInRegion: $previousInRegion !== null,
            nextInRegion: $nextInRegion !== null,
            previousLabel: $previousInTrip ? 'Vorig deel van deze reis' : self::label('Vorig', $blog, $previousInRegion),
            nextLabel: $nextInTrip ? 'Volgend deel van deze reis' : self::label('Volgend', $blog, $nextInRegion),
            more: $more,
            moreInRegion: $more->isNotEmpty() && $more->every(fn (Blog $other) => self::sameRegion($blog, $other)),
        );
    }

    /**
     * "Vorig verhaal uit Thailand" for the same country, "in de buurt" across a border,
     * or just "Vorig verhaal" when the story comes from somewhere else.
     */
    private static function label(string $direction, Blog $blog, ?Blog $inRegion): string
    {
        if (! $inRegion) {
            return "{$direction} verhaal";
        }

        $sharedCountry = $blog->countries->first(
            fn ($country) => $inRegion->countries->contains('id', $country->id)
        );

        return $sharedCountry
            ? "{$direction} verhaal uit {$sharedCountry->name}"
            : "{$direction} verhaal in de buurt";
    }

    public static function sameRegion(Blog $a, Blog $b): bool
    {
        if ($a->countries->pluck('id')->intersect($b->countries->pluck('id'))->isNotEmpty()) {
            return true;
        }

        $distance = self::distanceKm($a, $b);

        return $distance !== null && $distance <= self::REGION_KM;
    }

    /**
     * The same trip and country weigh most, then closeness, then shared categories and tags.
     */
    private static function score(Blog $blog, Blog $other): float
    {
        $score = 0.0;

        if ($blog->trip_id && $blog->trip_id === $other->trip_id) {
            $score += 6;
        }

        if ($blog->countries->pluck('id')->intersect($other->countries->pluck('id'))->isNotEmpty()) {
            $score += 5;
        }

        $distance = self::distanceKm($blog, $other);
        if ($distance !== null && $distance < self::REGION_KM) {
            $score += 3 * (1 - $distance / self::REGION_KM);
        }

        $score += 2 * $blog->categories->pluck('id')->intersect($other->categories->pluck('id'))->count();
        $score += $blog->tags->pluck('id')->intersect($other->tags->pluck('id'))->count();

        return $score;
    }

    /**
     * Great-circle distance between the two map pins, or null when one has no position.
     */
    public static function distanceKm(Blog $a, Blog $b): ?float
    {
        $from = $a->mapPosition();
        $to = $b->mapPosition();

        if (! $from || ! $to) {
            return null;
        }

        [$lat1, $lng1, $lat2, $lng2] = array_map('deg2rad', [$from[0], $from[1], $to[0], $to[1]]);

        $h = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(($lng2 - $lng1) / 2) ** 2;

        return 6371 * 2 * asin(min(1, sqrt($h)));
    }

    private static function latestBefore(Collection $blogs, CarbonInterface $date): ?Blog
    {
        return $blogs
            ->filter(fn (Blog $other) => $other->publishedDate()->lt($date))
            ->sortByDesc(fn (Blog $other) => $other->publishedDate())
            ->first();
    }

    private static function earliestAfter(Collection $blogs, CarbonInterface $date): ?Blog
    {
        return $blogs
            ->filter(fn (Blog $other) => $other->publishedDate()->gt($date))
            ->sortBy(fn (Blog $other) => $other->publishedDate())
            ->first();
    }
}
