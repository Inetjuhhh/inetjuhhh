<?php

namespace App\Support;

use App\Filament\Curator\MediaTable;
use App\Models\Trip;
use Awcodes\Curator\Models\Media;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which trip a photo belongs to: the trip it is linked to (when uploading, or later in the media
 * library), and otherwise the trip of the blog it is in. So photos already in a trip's blogs belong
 * to that trip without being linked one by one, and a link chosen by hand always wins.
 */
class PhotoTrips
{
    /**
     * The trips to choose from, the latest at the top.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return Trip::query()->orderByDesc('starts_on')->orderByDesc('id')->pluck('name', 'id')->all();
    }

    /**
     * The trip new photos probably belong to: the one going on now, or else the one that started last.
     */
    public static function suggestion(): ?int
    {
        return Trip::query()->whereDate('starts_on', '<=', today())->orderByDesc('starts_on')->orderByDesc('id')->value('id');
    }

    /**
     * Only the photos of this trip.
     */
    public static function scope(Builder $query, int | string $tripId): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->where('trip_id', $tripId)
            ->orWhere(fn (Builder $query) => $query
                ->whereNull('trip_id')
                ->whereHas('blogs', fn (Builder $blogs) => $blogs->where('trip_id', $tripId))));
    }

    /**
     * The photos of this trip that are used nowhere yet first, then the rest of the trip, then all others.
     */
    public static function orderTripFirst(Builder $query, int | string $tripId): Builder
    {
        $tripId = (int) $tripId;
        $media = $query->qualifyColumn('id');
        $own = $query->qualifyColumn('trip_id');

        // The same rule as scope(): linked to the trip, or else in one of its blogs
        $inTrip = "({$own} = {$tripId} or ({$own} is null and exists (select 1 from blog_media"
            . " join blogs on blogs.id = blog_media.blog_id where blog_media.media_id = {$media} and blogs.trip_id = {$tripId})))";
        $used = MediaTable::usedSql($query);

        return $query->orderByRaw("case when {$inTrip} and not {$used} then 0 when {$inTrip} then 1 else 2 end");
    }

    /**
     * Links photos to a trip (or to none: then the trip of their blog counts again).
     * A plain update: nothing about the photo files changes.
     *
     * @param  iterable<int|string>  $mediaIds
     */
    public static function link(iterable $mediaIds, int | string | null $tripId): int
    {
        $ids = collect($mediaIds)->filter()->values()->all();

        if ($ids === []) {
            return 0;
        }

        return Media::query()->whereKey($ids)->update(['trip_id' => filled($tripId) ? (int) $tripId : null]);
    }

    public static function tripOf(Media $media): ?Trip
    {
        return $media->trip ?? $media->blogs->first()?->trip;
    }
}
