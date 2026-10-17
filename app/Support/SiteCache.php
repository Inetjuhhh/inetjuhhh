<?php

namespace App\Support;

use App\Models\Blog;
use App\Models\Country;
use App\Models\Trip;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * What pages show but rarely changes, remembered instead of worked out on each visit: the countries
 * and trips in the menu, the pins of the travel map, and the related blogs under each story.
 *
 * Every key carries a version number. Forgetting everything is raising that number: the old keys are
 * never asked for again and expire by themselves, however many related-blog lists there are.
 * That happens as soon as a blog, trip or country is saved or deleted (see AppServiceProvider), and
 * when a planned blog goes online (NewBlogNotifier::sendDue, every five minutes). After an hour at
 * the latest everything is worked out again anyway, as a safety net.
 */
class SiteCache
{
    private const TTL = 3600;

    /**
     * Countries with at least one story visitors can read, A to Z.
     *
     * @return Collection<int, Country>
     */
    public static function menuCountries(): Collection
    {
        return static::remember('menu-countries', fn () => Country::whereHas('blogs', fn ($query) => $query->published())
            ->orderBy('name')
            ->get());
    }

    /**
     * Trips with at least one story visitors can read, newest first.
     *
     * @return Collection<int, Trip>
     */
    public static function menuTrips(): Collection
    {
        return static::remember('menu-trips', fn () => Trip::whereHas('blogs', fn ($query) => $query->published())
            ->orderByDesc('starts_on')
            ->get());
    }

    /**
     * The travel timeline on the Reizen page, oldest first: the trips with stories online, each with its
     * photo (see Trip::coverMedia), then the trips still to come (no photo yet, a countdown instead).
     *
     * @return Collection<int, array{name: string, url: ?string, city: bool, month: ?string, cover: ?string, upcoming: bool, starts_on: ?string}>
     */
    public static function timeline(): Collection
    {
        return static::remember('timeline', function () {
            $past = static::menuTrips()->load('coverImage')->reject(fn (Trip $trip) => $trip->isUpcoming())
                ->sortBy(fn (Trip $trip) => $trip->starts_on?->timestamp ?? 0)
                ->map(function (Trip $trip) {
                    $photo = $trip->coverMedia();

                    return static::timelineItem($trip, $photo ? BlogImages::mediaUrl($photo, 300) : asset('images/cover-placeholder.svg'));
                });

            // A coming trip links to its stories once there are any (a "we're packing" story, say)
            $hasStories = static::menuTrips()->pluck('id');
            $coming = Trip::upcoming()->get()->map(fn (Trip $trip) => static::timelineItem($trip, null, $hasStories->contains($trip->id)));

            return $past->concat($coming)->values();
        });
    }

    private static function timelineItem(Trip $trip, ?string $cover, bool $linked = true): array
    {
        return [
            'name' => $trip->name,
            'url' => $linked ? $trip->filterUrl() : null,
            'city' => $trip->kind === Trip::KIND_CITY,
            'month' => $trip->starts_on?->translatedFormat('M Y'),
            'cover' => $cover,
            'upcoming' => $trip->isUpcoming(),
            'starts_on' => $trip->starts_on?->toDateString(),
        ];
    }

    /**
     * The next trip to count down to: the first one still to come that has no story online yet
     * (the countdown stops once the first story of the trip is published).
     *
     * @return array{name: string, description: ?string, starts_on: string, counting_since: string}|null
     */
    public static function comingTrip(): ?array
    {
        return static::remember('coming-trip', function () {
            $trip = Trip::upcoming()->whereDoesntHave('blogs', fn ($query) => $query->published())->first();

            return $trip ? [
                'name' => $trip->name,
                'description' => $trip->description,
                'starts_on' => $trip->starts_on->toDateString(),
                // Where the dashed line starts: the day the trip was added
                'counting_since' => $trip->created_at->toDateString(),
            ] : null;
        });
    }

    /**
     * The routes of trips still to come, for the dashed lines on the travel map.
     *
     * @return Collection<int, array{name: string, when: ?string, stops: list<array{name: string, lat: float, lng: float}>}>
     */
    public static function plannedRoutes(): Collection
    {
        return static::remember('planned-routes', fn () => Trip::upcoming()->get()
            ->map(fn (Trip $trip) => ['name' => $trip->name, 'when' => $trip->period(), 'stops' => $trip->plannedStops()])
            ->filter(fn (array $trip) => count($trip['stops']) >= 2)
            ->values());
    }

    /**
     * Every pin of the travel map.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function mapMarkers(): Collection
    {
        return static::remember('map-markers', fn () => TravelMap::markers());
    }

    /**
     * The "read on" suggestions under a blog.
     */
    public static function related(Blog $blog): mixed
    {
        return static::remember("related:{$blog->id}", fn () => RelatedBlogs::for($blog));
    }

    public static function forget(): void
    {
        // Counting up in the cache itself, so two saves at the same moment can't both write "3"
        Cache::add('site:version', 1);
        static::$version = (int) Cache::increment('site:version');
    }

    private static function remember(string $key, \Closure $callback): mixed
    {
        return Cache::remember('site:' . static::version() . ":{$key}", self::TTL, $callback);
    }

    /** The version, looked up once per request instead of for every key */
    private static ?int $version = null;

    private static function version(): int
    {
        return static::$version ??= (int) Cache::get('site:version', 1);
    }
}
