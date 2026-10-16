<?php

namespace App\Support;

use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Counts how often a blog is read, without cookies and without storing anything about the reader.
 *
 * Only one number per blog per day goes into the database. So that refreshing the page doesn't count
 * again, a scrambled code of the visit (address + browser + blog + day) is kept in the cache until the
 * end of the day; it can't be turned back into an address, and it's never stored anywhere else.
 * Search engines and other bots don't count, and neither does the logged-in owner.
 */
class ViewCounter
{
    private const BOTS = '/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|telegram|preview|curl|wget|python|httpclient|headless|lighthouse|monitor/i';

    public static function record(Request $request, Blog $blog): void
    {
        $agent = (string) $request->userAgent();

        if ($agent === '' || preg_match(self::BOTS, $agent) || $request->user()) {
            return;
        }

        $today = now()->toDateString();
        $visit = 'viewed:' . hash_hmac('sha256', "{$request->ip()}|{$agent}|{$blog->id}|{$today}", config('app.key'));

        // Counted already today (a refresh, or reading on after going back)
        if (! Cache::add($visit, true, now()->endOfDay())) {
            return;
        }

        DB::table('blog_views')->upsert(
            [['blog_id' => $blog->id, 'date' => $today, 'views' => 1]],
            ['blog_id', 'date'],
            ['views' => DB::raw('views + 1')],
        );
    }
}
