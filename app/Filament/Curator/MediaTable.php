<?php

namespace App\Filament\Curator;

use App\Models\Blog;
use App\Models\Country;
use App\Models\Page;
use App\Models\Trip;
use App\Support\Avatar;
use App\Support\PhotoBlogs;
use App\Support\PhotoTrips;
use Awcodes\Curator\Models\Media;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Awcodes\Curator\Resources\Media\Tables\MediaTable as CuratorMediaTable;
use Filament\Tables\Columns\Layout\View;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Curator's media library, ordered by the blogs the photos are in: each photo shows its place and trip,
 * there are filters on trip, country and year, and the search also looks in the photo description and
 * in the blog's title and place. Set in AppServiceProvider.
 */
class MediaTable extends CuratorMediaTable
{
    public static function configure(Table $table): Table
    {
        return parent::configure($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['trip', 'blogs' => fn ($blogs) => $blogs->with(['trip', 'countries'])->orderBy('published_at')]))
            ->searchUsing(fn (Builder $query, string $search) => static::search($query, $search))
            // Select photos (or all of them, also on other pages) and link them to a trip in one go
            ->pushToolbarActions([
                BulkAction::make('linkTrip')
                    ->label('Koppel aan reis')
                    ->icon(Heroicon::OutlinedMap)
                    ->color('gray')
                    ->modalHeading('Koppel aan reis')
                    ->modalSubmitActionLabel('Koppelen')
                    ->schema([
                        ListMedia::tripField()
                            ->default(fn () => PhotoTrips::suggestion())
                            ->helperText('Leeg: de koppeling gaat eraf, dan telt weer de reis van de blog waar de foto in staat.'),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $count = PhotoTrips::link($records->modelKeys(), $data['trip_id'] ?? null);
                        $trip = filled($data['trip_id'] ?? null) ? Trip::find($data['trip_id'])?->name : null;

                        Notification::make()->success()
                            ->title($count === 1 ? '1 foto' : "{$count} foto's")
                            ->body($trip ? "Gekoppeld aan {$trip}." : 'Niet meer aan een reis gekoppeld.')
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->filters([
                SelectFilter::make('trip')
                    ->label('Reis')
                    ->options(fn () => PhotoTrips::options())
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($query, $trip) => PhotoTrips::scope($query, $trip))),
                SelectFilter::make('country')
                    ->label('Land')
                    ->options(fn () => Country::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($query, $country) => $query->whereHas('blogs.countries', fn ($countries) => $countries->whereKey($country)))),
                SelectFilter::make('year')
                    ->label('Jaar')
                    ->options(fn () => static::years())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($query, $year) => $query->whereHas('blogs', fn ($blogs) => $blogs->whereYear('published_at', $year)))),
                Filter::make('unused')
                    ->label('Nergens gebruikt')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->doesntHave('blogs')->whereKeyNot(array_keys(static::otherUses()))),
            ], layout: FiltersLayout::AboveContent)
            // The photos used nowhere yet at the top, the newest first: those are the ones still to place
            ->defaultSort(fn (Builder $query) => static::orderUnusedFirst($query)->orderByDesc('created_at'))
            ->filtersFormColumns(['md' => 2, 'xl' => 4])
            ->deferFilters(false)
            // No "all": 4000+ photos on one page ran PHP out of memory (500 on /admin/media). Enforced, so a
            // session that still remembers "all" (Filament keeps the choice) falls back to 48 instead
            ->paginationPageOptions([12, 24, 48, 96])
            ->enforcePaginationPageOptions()
            ->defaultPaginationPageOption(48);
    }

    public static function getDefaultGridTableColumns(): array
    {
        return [
            View::make('filament.curator.grid-column'),
            ...array_slice(parent::getDefaultGridTableColumns(), 1),
        ];
    }

    public static function getDefaultTableColumns(): array
    {
        $columns = parent::getDefaultTableColumns();

        array_splice($columns, 2, 0, [
            TextColumn::make('in_blog')
                ->label('In blog')
                ->state(fn (Media $record) => static::placeOf($record)['blog'] ?? static::otherUses()[$record->id] ?? 'Nergens gebruikt')
                ->description(fn (Media $record) => collect(static::placeOf($record))->only(['place', 'trip'])->filter()->join(' · '))
                ->wrap(),
        ]);

        return $columns;
    }

    /**
     * Where the photo is, from the first blog it is in: "📍 Italië – Toscane", "🧭 Italië 2026".
     *
     * The trip is the one the photo is linked to, or else the blog's (see PhotoTrips); a photo
     * that is only linked to a trip, not yet in a blog, has just the trip.
     *
     * @return array{blog: ?string, place: ?string, trip: ?string, more: int}|null
     */
    public static function placeOf(Media $media): ?array
    {
        $blog = $media->blogs->first();
        $trip = PhotoTrips::tripOf($media);

        if (! $blog && ! $trip) {
            return null;
        }

        $place = $blog ? collect($blog->placeTags())->pluck('label')->first() : null;

        return [
            'blog' => $blog?->title,
            'place' => $place ? '📍 ' . $place : null,
            'trip' => $trip ? '🧭 ' . $trip->name : null,
            'more' => max(0, $media->blogs->count() - 1),
        ];
    }

    /**
     * Photos used nowhere first (not in a blog, nor elsewhere: see otherUses), the same as the filter "Nergens gebruikt".
     * Also used by the photo window in a blog (CuratorPanel).
     */
    public static function orderUnusedFirst(Builder $query): Builder
    {
        return $query->orderByRaw('case when ' . static::usedSql($query) . ' then 1 else 0 end');
    }

    /**
     * SQL that is true for a photo used somewhere: in a blog, or elsewhere (see otherUses).
     */
    public static function usedSql(Builder $query): string
    {
        $id = $query->qualifyColumn('id');
        $used = "exists (select 1 from blog_media where blog_media.media_id = {$id})";

        if ($elsewhere = array_map('intval', array_keys(static::otherUses()))) {
            $used .= " or {$id} in (" . implode(',', $elsewhere) . ')';
        }

        return "({$used})";
    }

    /**
     * Photos used outside the blogs, so they don't count as unused: trip covers, the pages (Ik & wij) and the avatar.
     * Worked out once per request.
     *
     * @return array<int, string> media id → where it is used
     */
    public static function otherUses(): array
    {
        return once(function () {
            $uses = Trip::whereNotNull('cover_image_id')->pluck('name', 'cover_image_id')
                ->map(fn (string $trip) => 'Omslag van ' . $trip)
                ->all();

            foreach (Page::all() as $page) {
                foreach (PhotoBlogs::idsInDoc($page->content) as $id) {
                    $uses[$id] ??= $page->title;
                }
            }

            if ($avatar = Avatar::mediaId()) {
                $uses[$avatar] = 'Avatar';
            }

            return $uses;
        });
    }

    // Every word has to be somewhere: in the photo's own texts, or in the title or place of a blog it is in
    private static function search(Builder $query, string $search): void
    {
        foreach (preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $word) . '%';

            $query->where(fn (Builder $query) => $query
                ->where('name', 'like', $like)
                ->orWhere('title', 'like', $like)
                ->orWhere('alt', 'like', $like)
                ->orWhere('caption', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhereHas('blogs', fn ($blogs) => $blogs->where('title', 'like', $like)->orWhere('location_name', 'like', $like)));
        }
    }

    /** @return array<int, int> */
    private static function years(): array
    {
        return Blog::whereNotNull('published_at')->pluck('published_at')
            ->map(fn ($date) => (int) $date->format('Y'))
            ->unique()->sortDesc()
            ->mapWithKeys(fn ($year) => [$year => $year])
            ->all();
    }
}
