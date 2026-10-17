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
     * The trips as cards on the Reizen page, newest first, each with its photo (see Trip::coverMedia).
     *
     * @return Collection<int, array{name: string, url: string, city: bool, period: ?string, cover: string}>
     */
    public static function tripCards(): Collection
    {
        return static::remember('trip-cards', fn () => static::menuTrips()->load('coverImage')->map(function (Trip $trip) {
            $photo = $trip->coverMedia();

            return [
                'name' => $trip->name,
                'url' => $trip->filterUrl(),
                'city' => $trip->kind === Trip::KIND_CITY,
                'period' => $trip->period(),
                'cover' => $photo ? BlogImages::mediaUrl($photo, 600) : asset('images/cover-placeholder.svg'),
            ];
        })->values());
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
