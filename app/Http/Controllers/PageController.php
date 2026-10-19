<?php

namespace App\Http\Controllers;

use App\Models\Page;

class PageController extends Controller
{
    public function about()
    {
        return view('pages.show')->with('page', Page::about());
    }

    public function privacy()
    {
        return view('pages.show', ['page' => Page::privacy(), 'eyebrow' => 'Even eerlijk', 'subscribe' => false]);
    }
}
