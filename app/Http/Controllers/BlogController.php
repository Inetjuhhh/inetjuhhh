<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Country;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index()
    {
        $blogs = Blog::orderBy('created_at', 'desc')->paginate(10);
        $countries = Country::all();

        return view('blogs.index')
            ->with('blogs', $blogs)
            ->with('countries', $countries);
    }


    public function show(string $id)
    {
        $shareComponent = \Jorenvh\Share\ShareFacade::page(
            'https://pranabkalita.com/posts/mastering-laravel-macros-a-comprehensive-guide',
            'Your share text comes here',
            )
            ->facebook()
            ->linkedin()
            ->reddit();
        $blog = Blog::findOrFail($id);
        return view('blogs.show')->with('blog', $blog)->with('shareComponent', $shareComponent);
    }

    public function blogCountry(string $id)
    {
        $blogs = Blog::whereHas('countries', function ($query) use ($id) {
            $query->where('country_id', $id);
        })->paginate(10);

        return view('blogs.index')->with('blogs', $blogs);
    }
}
