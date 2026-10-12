<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\BlogLike;
use App\Support\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    /**
     * Heart or un-heart a blog. Returns the new state and count for the button.
     *
     * The button says which state it wants instead of "toggle": a tab that was open before the
     * heart was given elsewhere (or a page from the back button) would otherwise undo it.
     */
    public function toggle(Request $request, Blog $blog): JsonResponse
    {
        abort_unless($blog->isPublished(), 404);

        $liked = $request->validate(['liked' => ['required', 'boolean']])['liked'];
        $visitor = Visitor::hash($request);

        if ($liked) {
            BlogLike::firstOrCreate(
                ['blog_id' => $blog->id, 'visitor_hash' => $visitor],
                ['ip_hash' => Visitor::ipHash($request)],
            );
        } else {
            $blog->likes()->where('visitor_hash', $visitor)->delete();
        }

        return response()->json([
            'liked' => (bool) $liked,
            'count' => $blog->likes()->count(),
        ]);
    }
}
