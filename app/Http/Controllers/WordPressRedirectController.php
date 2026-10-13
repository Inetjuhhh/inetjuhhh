<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Country;
use App\Support\BlogImages;
use App\Support\WordPress\WordPressUploads;
use Awcodes\Curator\Models\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Links to the old WordPress blog keep working: each one is sent on, permanently (301), to where
 * that content is now. Search engines then move the old link's place in their results along.
 * Blogs and photos are found through storage/app/wordpress/redirects.json, written by the import.
 */
class WordPressRedirectController extends Controller
{
    /**
     * /2019/01/12/jutten-of-jatten/ → the blog
     */
    public function post(string $year, string $month, string $day, string $slug): RedirectResponse
    {
        $wordpressId = $this->map('posts')[mb_strtolower($slug)] ?? null;

        $blog = ($wordpressId ? Blog::published()->where('wordpress_id', $wordpressId)->first() : null)
            ?? Blog::published()->where('slug', $slug)->first();

        abort_unless($blog, 404);

        return redirect()->route('blogs.show', $blog, 301);
    }

    /**
     * /?p=2232 (WordPress' short link) → the blog
     */
    public function shortLink(Request $request): RedirectResponse
    {
        $blog = Blog::published()->where('wordpress_id', (int) $request->query('p'))->first();

        abort_unless($blog, 404);

        return redirect()->route('blogs.show', $blog, 301);
    }

    /**
     * /wp-content/uploads/2019/01/foto-768x1024.jpg → the new photo
     */
    public function upload(string $path): RedirectResponse
    {
        $mediaId = $this->map('uploads')[WordPressUploads::key($path)] ?? null;
        $media = $mediaId ? Media::find($mediaId) : null;

        abort_unless($media, 404);

        return redirect()->to(BlogImages::url($media->path), 301);
    }

    /**
     * /category/reizen/thailand/ → the country page, the map, or the search for that word
     */
    public function category(string $path): RedirectResponse
    {
        $slug = Str::afterLast(trim($path, '/'), '/');
        $config = config('wordpress-import');
        $matches = fn (string $name) => Str::slug($name) === $slug;

        // A country, or a city of a country
        $country = collect($config['countries'])->first(fn ($to, $from) => $matches($from))
            ?? collect($config['cities'])->first(fn ($to, $from) => $matches($from));
        if ($country && ($model = Country::where('name', $country)->first())) {
            return redirect()->route('blogs.blogCountry', $model->id, 301);
        }

        // Travel is best seen on the map; the other topics through the search
        $category = collect($config['categories'])->first(fn ($to, $from) => $matches($from));
        if ($category === 'Travel') {
            return redirect()->route('blogs.map', status: 301);
        }

        if ($category || ! in_array($slug, array_map(fn ($c) => Str::slug($c), $config['ignore']), true)) {
            return redirect()->route('search', ['q' => $category ?? str_replace('-', ' ', $slug)], 301);
        }

        return redirect()->route('blogs.index', status: 301);
    }

    /**
     * /tag/sport/ → the search for that word
     */
    public function tag(string $slug): RedirectResponse
    {
        return redirect()->route('search', ['q' => str_replace('-', ' ', $slug)], 301);
    }

    /**
     * /page/3/ → page 3 of the overview
     */
    public function page(int $number): RedirectResponse
    {
        return redirect()->route('blogs.index', $number > 1 ? ['page' => $number] : [], 301);
    }

    /**
     * The old pages (/over-ine/, /travel/, …), to where config/wordpress-import.php 'page_redirects' says
     */
    public function oldPage(string $slug): RedirectResponse
    {
        return redirect()->to(config("wordpress-import.page_redirects.{$slug}", '/'), 301);
    }

    /**
     * @return array<string, int>
     */
    private function map(string $part): array
    {
        $file = config('wordpress-import.directory') . '/redirects.json';

        if (! is_file($file)) {
            return [];
        }

        // Read once per change of the file, not on every request
        return Cache::remember('wordpress-redirects:' . filemtime($file), now()->addDay(), fn () => json_decode(file_get_contents($file), true) ?: [])[$part] ?? [];
    }
}
