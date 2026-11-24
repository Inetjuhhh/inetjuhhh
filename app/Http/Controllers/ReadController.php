<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Support\ViewCounter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Called by the blog page once the story has been read for 15 seconds (see ViewCounter).
 */
class ReadController
{
    public function store(Request $request, string $slug): Response
    {
        $blog = Blog::published()->where('slug', $slug)->first();

        if ($blog) {
            ViewCounter::record(
                $request,
                $blog,
                referrer: mb_substr((string) $request->input('referrer'), 0, 500),
                mark: mb_substr((string) $request->input('bron'), 0, 30),
            );
        }

        // Always the same answer, so nothing can be learned from it
        return response()->noContent();
    }
}
