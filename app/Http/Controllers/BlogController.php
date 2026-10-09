<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Country;

class BlogController extends Controller
{
    public function index()
    {
        $blogs = Blog::published()->newestFirst()->paginate(10);

        return view('blogs.index')->with('blogs', $blogs);
    }

    public function show(string $slug)
    {
        $blog = Blog::published()->where('slug', $slug)->first();

        // Old links used the id (/blogs/12); send those to the new address
        if (! $blog && ctype_digit($slug) && ($old = Blog::published()->find($slug))) {
            return redirect()->route('blogs.show', $old, 301);
        }

        abort_unless($blog, 404);

        return view('blogs.show')->with('blog', $blog);
    }

    public function preview(Blog $blog)
    {
        return view('blogs.show')
            ->with('blog', $blog)
            ->with('isPreview', true);
    }

    public function blogCountry(string $id)
    {
        $country = Country::findOrFail($id);
        $blogs = Blog::published()->whereHas('countries', function ($query) use ($id) {
            $query->where('country_id', $id);
        })->newestFirst()->paginate(10);

        return view('blogs.index')
            ->with('blogs', $blogs)
            ->with('country', $country);
    }
}
