<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Country;
use App\Models\LegacyLink;
use App\Support\BlogImages;
use App\Support\LegacyLinks\UploadKey;
use Awcodes\Curator\Models\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Links to the old WordPress blog keep working: each one is sent on, permanently (301), to where
 * that content is now. Search engines then move the old link's place in their results along.
 * Blogs and photos are found in the legacy_links table, filled by the WordPress import.
 *
 * Keep this for years: old links in search results, on Pinterest and in mails keep coming in.
 */
class LegacyLinkController extends Controller
{
    /**
     * /2019/01/12/jutten-of-jatten/ → the blog (also when its link was changed later)
     */
    public function post(string $year, string $month, string $day, string $slug): RedirectResponse
    {
        $blogId = LegacyLink::target(LegacyLink::POST, mb_strtolower($slug));

        $blog = ($blogId ? Blog::published()->find($blogId) : null)
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
        $mediaId = LegacyLink::target(LegacyLink::UPLOAD, UploadKey::from($path));
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

        // Set in config/legacy-links.php: a category that has its own place now
        if ($to = config("legacy-links.categories.{$slug}")) {
            return redirect()->to($to, 301);
        }

        // A country ("thailand", "italie"), or a city of one ("rome" → Italië)
        $countryName = config("legacy-links.cities.{$slug}");
        $country = Country::all()->first(fn (Country $c) => $countryName ? $c->name === $countryName : Str::slug($c->name) === $slug);
        if ($country) {
            return redirect()->route('blogs.blogCountry', $country->id, 301);
        }

        // Anything else: the search for that word
        return redirect()->route('search', ['q' => str_replace('-', ' ', $slug)], 301);
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
        return redirect()->to(config("legacy-links.pages.{$slug}", '/'), 301);
    }
}
