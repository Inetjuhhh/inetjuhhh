<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Country;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index()
    {
        $blogs = Blog::all();
        $countries = Country::all();

        return view('blogs.index')->with('blogs', $blogs)->with('countries', $countries);
    }

    public function show(string $id)
    {
        $blog = Blog::findOrFail($id);
        return view('blogs.show')->with('blog', $blog);
    }

    public function blogCountry(string $id)
    {
        //get the blogs that belong to the country ($id) with the pivot table blog_country (country_id)
        $blogs = Blog::whereHas('countries', function ($query) use ($id) {
            $query->where('country_id', $id);
        })->get();

        return view('blogs.index')->with('blogs', $blogs);
    }
}
