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
     */
    public function toggle(Request $request, Blog $blog): JsonResponse
    {
        abort_unless($blog->isPublished(), 404);

        $visitor = Visitor::hash($request);
        $like = $blog->likes()->where('visitor_hash', $visitor)->first();

        if ($like) {
            $like->delete();
        } else {
            BlogLike::firstOrCreate(
                ['blog_id' => $blog->id, 'visitor_hash' => $visitor],
                ['ip_hash' => Visitor::ipHash($request)],
            );
        }

        return response()->json([
            'liked' => ! $like,
            'count' => $blog->likes()->count(),
        ]);
    }
}
