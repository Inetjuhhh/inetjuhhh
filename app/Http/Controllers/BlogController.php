<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Country;
use App\Models\Tag;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Support\SiteCache;
use App\Support\TravelMap;
use App\Support\ViewCounter;
use App\Support\Visitor;

class BlogController extends Controller
{
    // Everything a blog card shows, loaded in one go instead of per card
    private const CARD_RELATIONS = ['countries', 'categories', 'trip', 'featuredImage', 'placed_by'];

    public function index(Request $request)
    {
        // An old WordPress short link (/?p=2232) goes to that blog
        if (ctype_digit((string) $request->query('p'))) {
            return app(LegacyLinkController::class)->shortLink($request);
        }

        // ?reis=vietnam-cambodja-2025 shows one trip, in reading order (oldest first)
        if ($request->filled('reis')) {
            $trip = Trip::where('slug', $request->query('reis'))->firstOrFail();

            return view('blogs.index')
                ->with('trip', $trip)
                ->with('blogs', $trip->blogs()->published()->oldestFirst()->with(self::CARD_RELATIONS)->paginate(12)->withQueryString());
        }

        $blogs = Blog::published()->newestFirst()->with(self::CARD_RELATIONS)->paginate(10);

        return view('blogs.index')
            ->with('blogs', $blogs)
            // The travel map teaser is only shown on the first page of the homepage
            ->with('mapMarkers', $blogs->onFirstPage() ? SiteCache::mapMarkers() : null);
    }

    public function map(Request $request)
    {
        $markers = SiteCache::mapMarkers();
        $tripSlugs = $markers->pluck('trip.slug')->filter()->unique();

        return view('blogs.map')
            ->with('markers', $markers)
            // Only trips that have stories on the map can be chosen
            ->with('trips', Trip::whereIn('slug', $tripSlugs)->orderByDesc('starts_on')->get())
            ->with('activeTrip', $tripSlugs->contains($request->query('reis')) ? $request->query('reis') : null);
    }

    public function show(Request $request, string $slug)
    {
        $blog = Blog::published()->where('slug', $slug)->first();

        // Old links used the id (/blogs/12); send those to the new address
        if (! $blog && ctype_digit($slug) && ($old = Blog::published()->find($slug))) {
            return redirect()->route('blogs.show', $old, 301);
        }

        abort_unless($blog, 404);

        // Counted only here, not for previews of drafts
        ViewCounter::record($request, $blog);

        return $this->showBlog($request, $blog);
    }

    public function preview(Request $request, Blog $blog)
    {
        return $this->showBlog($request, $blog)->with('isPreview', true);
    }

    private function showBlog(Request $request, Blog $blog)
    {
        $visitor = Visitor::existingHash($request);

        // Everything the page shows of this blog, in one go instead of piece by piece
        $blog->load(['countries', 'categories', 'tags', 'trip', 'featuredImage', 'placed_by'])->loadCount('likes');

        return view('blogs.show')
            ->with('blog', $blog)
            ->with('liked', $visitor !== null && $blog->likes()->where('visitor_hash', $visitor)->exists())
            ->with('comments', $blog->comments()->get())
            ->with('tripMarkers', $blog->trip ? TravelMap::forTrip($blog->trip, $blog) : collect())
            ->with('related', SiteCache::related($blog));
    }

    public function blogCountry(string $id)
    {
        $country = Country::findOrFail($id);
        $blogs = Blog::published()->whereHas('countries', function ($query) use ($id) {
            $query->where('country_id', $id);
        })->newestFirst()->with(self::CARD_RELATIONS)->paginate(10);

        return view('blogs.index')
            ->with('blogs', $blogs)
            ->with('country', $country);
    }

    public function category(Request $request, Category $category)
    {
        $inCategory = fn ($query) => $query->whereKey($category->id);

        // What the stories in this category are about: their tags, with how many stories each
        $subjects = Tag::query()
            ->join('blog_tag', 'blog_tag.tag_id', '=', 'tags.id')
            ->whereIn('blog_tag.blog_id', Blog::published()->whereHas('categories', $inCategory)->select('blogs.id'))
            ->groupBy('tags.id', 'tags.name')
            ->orderByDesc('count')
            ->get(['tags.id', 'tags.name', DB::raw('count(*) as count')])
            ->map(fn (Tag $tag) => ['id' => $tag->id, 'name' => Str::ucfirst($tag->name), 'slug' => Str::slug($tag->name), 'count' => (int) $tag->count]);

        // ?onderwerp=familie: only the stories about that
        $subject = $subjects->firstWhere('slug', (string) $request->query('onderwerp'));

        $blogs = Blog::published()->whereHas('categories', $inCategory)
            ->when($subject, fn ($query) => $query->whereHas('tags', fn ($query) => $query->whereKey($subject['id'])))
            ->newestFirst()->with(self::CARD_RELATIONS)->paginate(10)->withQueryString();

        return view('blogs.index')
            ->with('blogs', $blogs)
            ->with('category', $category)
            ->with('subject', $subject)
            ->with('tripCards', $category->slug === 'reizen' ? SiteCache::tripCards() : collect())
            ->with('mapMarkers', $category->slug === 'reizen' ? SiteCache::mapMarkers() : null)
            // The 20 most written about (Travel has over 70: too many to read as bubbles), plus the chosen one
            ->with('subjects', $subjects->take(20)->when($subject && ! $subjects->take(20)->contains('slug', $subject['slug']), fn ($top) => $top->push($subject))->map(fn (array $item) => [
                'name' => $item['name'],
                'count' => $item['count'],
                'url' => route('blogs.category', [$category, 'onderwerp' => $item['slug']]),
                'active' => $item['slug'] === ($subject['slug'] ?? null),
            ])->values());
    }
}
