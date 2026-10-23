<?php

namespace App\Filament\Curator;

use App\Models\Blog;
use App\Models\Country;
use App\Models\Page;
use App\Models\Trip;
use App\Support\Avatar;
use App\Support\PhotoBlogs;
use Awcodes\Curator\Models\Media;
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['blogs' => fn ($blogs) => $blogs->with(['trip', 'countries'])->orderBy('published_at')]))
            ->searchUsing(fn (Builder $query, string $search) => static::search($query, $search))
            ->filters([
                SelectFilter::make('trip')
                    ->label('Reis')
                    ->options(fn () => Trip::orderByDesc('starts_on')->pluck('name', 'id'))
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($query, $trip) => $query->whereHas('blogs', fn ($blogs) => $blogs->where('trip_id', $trip)))),
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
            ->filtersFormColumns(['md' => 2, 'xl' => 4])
            ->deferFilters(false)
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
     * @return array{blog: string, place: ?string, trip: ?string, more: int}|null
     */
    public static function placeOf(Media $media): ?array
    {
        $blog = $media->blogs->first();

        if (! $blog) {
            return null;
        }

        $place = collect($blog->placeTags())->pluck('label')->first();

        return [
            'blog' => $blog->title,
            'place' => $place ? '📍 ' . $place : null,
            'trip' => $blog->trip ? '🧭 ' . $blog->trip->name : null,
            'more' => $media->blogs->count() - 1,
        ];
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
