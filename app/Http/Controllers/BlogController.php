<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Country;
use App\Models\Trip;
use Illuminate\Http\Request;
use App\Support\RelatedBlogs;
use App\Support\TravelMap;
use App\Support\Visitor;

class BlogController extends Controller
{
    // Everything a blog card shows, loaded in one go instead of per card
    private const CARD_RELATIONS = ['countries', 'categories', 'trip', 'featuredImage', 'media', 'placed_by'];

    public function index(Request $request)
    {
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
            ->with('mapMarkers', $blogs->onFirstPage() ? TravelMap::markers() : null);
    }

    public function map(Request $request)
    {
        $markers = TravelMap::markers();
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

        return $this->showBlog($request, $blog);
    }

    public function preview(Request $request, Blog $blog)
    {
        return $this->showBlog($request, $blog)->with('isPreview', true);
    }

    private function showBlog(Request $request, Blog $blog)
    {
        $visitor = Visitor::existingHash($request);

        return view('blogs.show')
            ->with('blog', $blog->loadCount('likes'))
            ->with('liked', $visitor !== null && $blog->likes()->where('visitor_hash', $visitor)->exists())
            ->with('comments', $blog->comments()->get())
            ->with('tripMarkers', $blog->trip ? TravelMap::forTrip($blog->trip, $blog) : collect())
            ->with('related', RelatedBlogs::for($blog));
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
}
