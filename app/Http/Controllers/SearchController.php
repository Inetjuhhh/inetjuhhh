<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Support\BlogSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The search bar: suggestions while typing, and all matching blogs after Enter.
 */
class SearchController extends Controller
{
    private const CARD_RELATIONS = ['countries', 'categories', 'trip', 'featuredImage', 'media', 'placed_by'];

    public function index(Request $request): View
    {
        $query = $this->query($request);
        $searched = $query;
        $blogs = BlogSearch::query($query)->with(self::CARD_RELATIONS)->paginate(12)->withQueryString();

        // Nothing found: try again with small typos fixed ("tailand" → "thailand")
        if ($blogs->isEmpty() && $query !== '' && ($corrected = BlogSearch::didYouMean($query))) {
            $searched = $corrected;
            $blogs = BlogSearch::query($corrected)->with(self::CARD_RELATIONS)->paginate(12)->withQueryString();
        }

        return view('blogs.index')
            ->with('blogs', $blogs)
            ->with('search', $query)
            ->with('searchedFor', $searched)
            ->with('places', BlogSearch::places($searched));
    }

    /**
     * At most six blogs plus matching countries and trips, for the list under the search bar.
     */
    public function suggest(Request $request): JsonResponse
    {
        $query = $this->query($request);

        if (mb_strlen($query) < 2) {
            return response()->json(['results' => [], 'places' => [], 'total' => 0, 'didYouMean' => null]);
        }

        $search = BlogSearch::query($query);
        $total = (clone $search)->count();

        $results = $search->with(['countries', 'featuredImage', 'media'])->limit(6)->get()
            ->map(fn (Blog $blog) => [
                'title' => $blog->title,
                'url' => route('blogs.show', $blog),
                'place' => collect($blog->placeTags())->pluck('label')->join(' · '),
                'date' => $blog->publishedDate()->translatedFormat('F Y'),
                'cover' => $blog->coverUrl(160, 160),
            ]);

        return response()->json([
            'results' => $results,
            'places' => BlogSearch::places($query),
            'total' => $total,
            'didYouMean' => $total === 0 ? BlogSearch::didYouMean($query) : null,
        ]);
    }

    private function query(Request $request): string
    {
        return mb_substr(trim((string) $request->query('q', '')), 0, 100);
    }
}
