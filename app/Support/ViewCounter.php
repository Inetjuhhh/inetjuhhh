<?php

namespace App\Support;

use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Counts how often a blog is really read, without storing anything about the reader.
 *
 * A read is counted by the browser itself, after the story has been on screen for 15 seconds (see
 * blogs/partials/read-counter): bots and link previews hardly ever run that, or wait for it. On top:
 * - known bots are left out by the name their browser gives;
 * - Ine doesn't count: neither while logged in, nor on a device she has once logged in on (a cookie,
 *   see MarkOwnerDevice);
 * - one read per reader per blog per day: a scrambled code of the visit (address + browser + blog +
 *   day) is kept in the cache until the end of the day; it can't be turned back into an address.
 *
 * Stored are only totals: per blog per day (blog_views), and per blog per day per country, city and
 * source (blog_view_stats). The address is used to look up the country and city, and then forgotten.
 */
class ViewCounter
{
    public const SECONDS = 15;

    public const OWNER_COOKIE = 'niet_meetellen';

    private const BOTS = '/bot|crawl|spider|slurp|scrap|archiver|facebookexternalhit|facebookcatalog|meta-externalagent|whatsapp|telegram|discord|slack|skype|linkedin|pinterest|embedly|preview|curl|wget|python|httpclient|http-client|go-http|java\/|okhttp|axios|node-fetch|undici|libwww|headless|phantom|selenium|puppeteer|playwright|lighthouse|pagespeed|gtmetrix|monitor|uptime|pingdom|statuscake|gptbot|chatgpt|oai-search|claude|anthropic|perplexity|bytespider|ccbot|cohere|diffbot|amazonbot|applebot|petalbot|yandex|baidu|sogou|ahrefs|semrush|mj12|dotbot|seokicks|serpstat|dataforseo|bingpreview|feedfetcher|rss|validator/i';

    public static function isBot(?string $agent): bool
    {
        return blank($agent) || preg_match(self::BOTS, $agent) === 1;
    }

    public static function isOwner(Request $request): bool
    {
        return $request->user() !== null || $request->cookie(self::OWNER_COOKIE) !== null;
    }

    /**
     * @return bool whether this read was counted
     */
    public static function record(Request $request, Blog $blog, ?string $referrer = null, ?string $mark = null): bool
    {
        $agent = (string) $request->userAgent();

        if (static::isBot($agent) || static::isOwner($request)) {
            return false;
        }

        $today = now()->toDateString();
        $visit = 'viewed:' . hash_hmac('sha256', "{$request->ip()}|{$agent}|{$blog->id}|{$today}", config('app.key'));

        // Counted already today (a refresh, or reading on after going back)
        if (! Cache::add($visit, true, now()->endOfDay())) {
            return false;
        }

        $location = ReaderLocation::lookup($request->ip());
        $source = ReadSource::from($referrer, $mark, (string) parse_url(config('app.url'), PHP_URL_HOST));

        DB::transaction(function () use ($blog, $today, $location, $source) {
            DB::table('blog_views')->upsert(
                [['blog_id' => $blog->id, 'date' => $today, 'views' => 1]],
                ['blog_id', 'date'],
                ['views' => DB::raw('views + 1')],
            );

            DB::table('blog_view_stats')->upsert(
                [['blog_id' => $blog->id, 'date' => $today, ...$location, 'source' => $source, 'views' => 1]],
                ['blog_id', 'date', 'country', 'city', 'source'],
                ['views' => DB::raw('views + 1')],
            );
        });

        return true;
    }

    /**
     * The day the new counting started: the first day with details. Before it, reads were counted
     * when the page opened, and are higher than they really were.
     */
    public static function countingSince(): ?string
    {
        return DB::table('blog_view_stats')->min('date');
    }
}
