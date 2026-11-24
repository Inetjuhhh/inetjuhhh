<?php

namespace App\Support;

use App\Models\Blog;
use App\Models\Country;
use App\Models\Subscriber;
use App\Models\Trip;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The numbers behind the dashboard and the statistics per blog, in one place, so both count the same.
 *
 * Reads: blog_views (a total per blog per day, since the start) and blog_view_stats (the same reads
 * with country, city and source, since the new counting started: see ViewCounter). Likes: blog_likes.
 */
class Statistics
{
    public const PERIODS = ['30' => '30 dagen', '365' => 'Jaar', 'all' => 'Altijd'];

    /**
     * The first day of a period ("30", "365" or "all"), or null for all time.
     */
    public static function from(string $period): ?CarbonInterface
    {
        return match ($period) {
            '30' => today()->subDays(29),
            '365' => today()->subDays(364),
            default => null,
        };
    }

    /**
     * Published blogs with their reads, likes and comments in a period, and likes per 100 readers.
     *
     * @param  'reads'|'likes'|'rate'|'comments'  $by
     * @return Collection<int, object{id: int, title: string, slug: string, published_at: ?string, trip: ?string, reads: int, likes: int, comments: int, rate: ?float}>
     */
    public static function blogs(string $period = 'all', string $by = 'reads', ?int $limit = null, ?string $search = null, ?int $tripId = null): Collection
    {
        $from = static::from($period)?->toDateString();

        $reads = DB::table('blog_views')->selectRaw('blog_id, SUM(views) as total')
            ->when($from, fn ($q) => $q->where('date', '>=', $from))->groupBy('blog_id');
        $likes = DB::table('blog_likes')->selectRaw('blog_id, COUNT(*) as total')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))->groupBy('blog_id');
        $comments = DB::table('responses')->selectRaw('blog_id, COUNT(*) as total')
            ->where('status', \App\Models\Response::APPROVED)->where('is_author', false)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))->groupBy('blog_id');

        $rows = Blog::published()
            ->leftJoinSub($reads, 'r', 'r.blog_id', '=', 'blogs.id')
            ->leftJoinSub($likes, 'l', 'l.blog_id', '=', 'blogs.id')
            ->leftJoinSub($comments, 'c', 'c.blog_id', '=', 'blogs.id')
            ->leftJoin('trips', 'trips.id', '=', 'blogs.trip_id')
            ->when(filled($search), fn ($q) => $q->where(fn ($q) => $q
                ->where('blogs.title', 'like', '%' . $search . '%')
                ->orWhere('trips.name', 'like', '%' . $search . '%')))
            ->when($tripId, fn ($q) => $q->where('blogs.trip_id', $tripId))
            ->get([
                'blogs.id', 'blogs.title', 'blogs.slug', 'blogs.published_at', 'trips.name as trip',
                DB::raw('COALESCE(r.total, 0) as read_total'), DB::raw('COALESCE(l.total, 0) as like_total'), DB::raw('COALESCE(c.total, 0) as comment_total'),
            ])
            ->map(fn (Blog $blog) => (object) [
                'id' => $blog->id,
                'title' => $blog->title,
                'slug' => $blog->slug,
                'published_at' => $blog->published_at,
                'trip' => $blog->trip,
                'reads' => (int) $blog->read_total,
                'likes' => (int) $blog->like_total,
                'comments' => (int) $blog->comment_total,
                // Not for a handful of reads: 2 likes of 3 readers says little
                'rate' => $blog->read_total >= 20 ? round($blog->like_total / $blog->read_total * 100, 1) : null,
            ]);

        $rows = $rows->sortByDesc(fn ($row) => match ($by) {
            'likes' => [$row->likes, $row->reads],
            'rate' => [$row->rate ?? -1, $row->reads],
            'comments' => [$row->comments, $row->reads],
            default => [$row->reads, $row->likes],
        })->values();

        return $limit ? $rows->take($limit) : $rows;
    }

    /**
     * Readers per country, each with their cities, the most first. Since the new counting.
     *
     * @return Collection<int, array{code: string, name: string, views: int, cities: list<array{name: string, views: int}>}>
     */
    public static function countries(string $period = '30', ?int $blogId = null): Collection
    {
        return static::details($period, $blogId)
            ->selectRaw('country, city, SUM(views) as total')->groupBy('country', 'city')->get()
            ->groupBy('country')
            ->map(fn (Collection $cities, string $code) => [
                'code' => $code,
                'name' => ReaderLocation::countryName($code),
                'views' => (int) $cities->sum('total'),
                'cities' => $cities->sortByDesc('total')->map(fn ($row) => [
                    'name' => $row->city !== '' ? $row->city : 'Onbekende plaats',
                    'views' => (int) $row->total,
                ])->values()->all(),
            ])
            ->sortByDesc('views')
            ->values();
    }

    /**
     * Readers per source (Google, Facebook, the newsletter, ...), the most first. Since the new counting.
     *
     * @return Collection<int, array{source: string, label: string, views: int}>
     */
    public static function sources(string $period = '30', ?int $blogId = null): Collection
    {
        return static::details($period, $blogId)
            ->selectRaw('source, SUM(views) as total')->groupBy('source')->get()
            ->map(fn ($row) => ['source' => $row->source, 'label' => ReadSource::label($row->source), 'views' => (int) $row->total])
            ->sortByDesc('views')
            ->values();
    }

    /**
     * Reads per day of the week (1 = Monday … 7 = Sunday), over the last 90 days.
     *
     * @return array<int, int>
     */
    public static function weekdays(int $days = 90): array
    {
        $perDay = DB::table('blog_views')->where('date', '>=', today()->subDays($days - 1)->toDateString())
            ->selectRaw('date, SUM(views) as total')->groupBy('date')->pluck('total', 'date');

        $week = array_fill(1, 7, 0);
        foreach ($perDay as $date => $total) {
            $week[(int) \Illuminate\Support\Carbon::parse($date)->isoWeekday()] += (int) $total;
        }

        return $week;
    }

    /**
     * Trips with their number of published blogs, and how often those were read and liked (all time).
     *
     * @return Collection<int, object{id: int, name: string, blogs: int, reads: int, likes: int}>
     */
    public static function trips(): Collection
    {
        $reads = DB::table('blog_views')->join('blogs', 'blogs.id', '=', 'blog_views.blog_id')
            ->selectRaw('blogs.trip_id, SUM(blog_views.views) as total')->groupBy('blogs.trip_id')->pluck('total', 'trip_id');
        $likes = DB::table('blog_likes')->join('blogs', 'blogs.id', '=', 'blog_likes.blog_id')
            ->selectRaw('blogs.trip_id, COUNT(*) as total')->groupBy('blogs.trip_id')->pluck('total', 'trip_id');

        return Trip::query()
            ->withCount(['blogs' => fn ($q) => $q->published()])
            ->get()
            ->filter(fn (Trip $trip) => $trip->blogs_count > 0)
            ->map(fn (Trip $trip) => (object) [
                'id' => $trip->id,
                'name' => $trip->name,
                'blogs' => $trip->blogs_count,
                'reads' => (int) ($reads[$trip->id] ?? 0),
                'likes' => (int) ($likes[$trip->id] ?? 0),
            ])
            ->sortByDesc(fn ($trip) => [$trip->blogs, $trip->reads])
            ->values();
    }

    /**
     * Per continent: the published blogs about it, and how often those were read (all time).
     * A blog about two continents counts for both.
     *
     * @return Collection<int, object{name: string, blogs: int, reads: int}>
     */
    public static function continents(): Collection
    {
        $blogContinents = DB::table('blog_country')
            ->join('countries', 'countries.id', '=', 'blog_country.country_id')
            ->join('blogs', 'blogs.id', '=', 'blog_country.blog_id')
            ->whereIn('blogs.id', Blog::published()->select('id'))
            ->whereNotNull('countries.continent')
            ->distinct()
            ->get(['blog_country.blog_id', 'countries.continent']);

        $reads = DB::table('blog_views')->whereIn('blog_id', $blogContinents->pluck('blog_id')->unique())
            ->selectRaw('blog_id, SUM(views) as total')->groupBy('blog_id')->pluck('total', 'blog_id');

        return $blogContinents->groupBy('continent')
            ->map(fn (Collection $rows, string $continent) => (object) [
                'name' => $continent,
                'blogs' => $rows->count(),
                'reads' => (int) $rows->sum(fn ($row) => $reads[$row->blog_id] ?? 0),
            ])
            ->sortBy(fn ($row) => array_search($row->name, Country::CONTINENTS))
            ->sortByDesc('blogs')
            ->values();
    }

    /**
     * Confirmed subscribers at the end of each of the last months.
     *
     * @return array<string, int> "2026-11" → total
     */
    public static function subscribersPerMonth(int $months = 12): array
    {
        $totals = [];
        foreach (range($months - 1, 0) as $ago) {
            $end = now()->startOfMonth()->subMonthsNoOverflow($ago)->endOfMonth();
            $totals[$end->format('Y-m')] = Subscriber::confirmed()->where('confirmed_at', '<=', $end)->count();
        }

        return $totals;
    }

    /**
     * Reads per day of one blog, from its publication on (at most 90 days).
     *
     * @return array<string, int> "2026-11-24" → reads
     */
    public static function blogPerDay(Blog $blog, int $days = 90): array
    {
        $start = ($blog->published_at ?? now())->copy()->startOfDay();
        $start = $start->max(today()->subDays($days - 1));
        $perDay = DB::table('blog_views')->where('blog_id', $blog->id)->where('date', '>=', $start->toDateString())
            ->pluck('views', 'date')->mapWithKeys(fn ($views, $date) => [substr((string) $date, 0, 10) => (int) $views]);

        $result = [];
        for ($day = $start->copy(); $day->lte(today()); $day->addDay()) {
            $result[$day->toDateString()] = $perDay[$day->toDateString()] ?? 0;
        }

        return $result;
    }

    private static function details(string $period, ?int $blogId): \Illuminate\Database\Query\Builder
    {
        $from = static::from($period)?->toDateString();

        return DB::table('blog_view_stats')
            ->when($from, fn ($q) => $q->where('date', '>=', $from))
            ->when($blogId, fn ($q) => $q->where('blog_id', $blogId));
    }
}
