<?php

namespace App\Console\Commands;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Country;
use App\Models\Tag;
use App\Models\Trip;
use App\Models\User;
use App\Support\WordPress\CommentImporter;
use App\Support\WordPress\ContentConverter;
use App\Support\Geocoder;
use App\Support\WordPress\MediaImporter;
use App\Support\WordPress\TitlePlace;
use App\Support\WordPress\WordPressUploads;
use Illuminate\Support\Facades\Cache;
use App\Support\WordPress\WxrReader;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Brings the old WordPress blog over. Safe to run again: blogs are matched on their WordPress id
 * and updated, so a second run (for example once the photos can come along) adds instead of duplicates.
 *
 *   php artisan wordpress:import --dry-run    shows what would happen, changes nothing
 *   php artisan wordpress:import              imports
 */
class ImportWordPress extends Command
{
    protected $signature = 'wordpress:import
        {file? : The export file (default: the newest .xml in storage/app/wordpress)}
        {--dry-run : Only show what would be imported}
        {--author= : Email address of the account the blogs are placed under}
        {--photos : Also download the photos, shrink them and put them in the blogs (resumable)}
        {--only= : Only these WordPress ids, comma separated (for testing)}';

    protected $description = 'Import blogs, categories, countries, tags and trips from a WordPress export';

    private array $config;

    /** @var list<string> */
    private array $knownCountries = [];

    private CommentImporter $comments;

    public function handle(): int
    {
        $this->config = config('wordpress-import');
        $dryRun = (bool) $this->option('dry-run');

        $file = $this->argument('file') ?? collect(glob(config('wordpress-import.directory') . '/*.xml'))->sortDesc()->first();
        if (! $file) {
            $this->error('No export file found in storage/app/wordpress.');

            return self::FAILURE;
        }

        $author = $this->option('author')
            ? User::where('email', $this->option('author'))->first()
            : User::whereIn('email', config('blog.admin_emails'))->first() ?? User::orderBy('id')->first();
        if (! $author) {
            $this->error('No account found to place the blogs under (use --author=email).');

            return self::FAILURE;
        }

        $this->info('Reading ' . basename($file) . ' …');
        $reader = new WxrReader($file);
        $this->line("  {$reader->removedCharacters} invalid characters removed, " . count($reader->posts) . ' posts, ' . count($reader->attachments) . ' media files');

        // Work out everything first; only then write, so a dry run shows exactly what a real run does
        // Every country name a title's [brackets] may end with
        $this->knownCountries = collect($this->config['countries'])->values()
            ->concat(array_values($this->config['cities']))
            ->concat($this->config['more_countries'] ?? [])
            ->concat(Country::pluck('name'))
            ->unique()->values()->all();

        $posts = collect($reader->posts)->map(fn (array $post) => $this->plan($post))->sortBy('published_at')->values();
        // The order matters: the most specific place wins, the trip only fills what's left
        $posts = $this->locate($posts);          // fixed places, [brackets] and titles, on the map
        $posts = $this->travelDays($posts);      // "Heenreis", "Home sweet home": from the blog next to it
        $trips = $this->trips($posts);
        [$posts, $trips] = $this->tripPlaces($posts, $trips);   // what's left: from its own trip
        $this->writePlaces($posts);

        // You, as the writer of comments: the admin addresses and the account the blogs go under
        $this->comments = new CommentImporter([...config('blog.admin_emails'), $author->email], $this->config['pending_comments'] ?? true);

        $this->report($posts, $trips, $author, $dryRun);

        if ($dryRun) {
            $this->newLine();
            $this->comment('Dry run: nothing was changed. Run without --dry-run to import.');

            return self::SUCCESS;
        }

        // With --photos every photo goes through the media importer; without, photos are left out for now
        // With --photos new photos are downloaded. Without it, photos imported before are still used:
        // otherwise running again (for the comments, say) would take every photo out of the blogs
        $mapFile = config('wordpress-import.directory') . '/media-map.json';
        $media = $this->option('photos') || is_file($mapFile)
            ? new MediaImporter($reader->attachments, $mapFile, $this->config['skip_media'], download: (bool) $this->option('photos'))
            : null;
        $converter = new ContentConverter($media ? fn (int $id) => $media->import($id) : null);

        $only = array_filter(array_map('intval', explode(',', (string) $this->option('only'))));
        if ($only) {
            $posts = $posts->filter(fn ($p) => in_array($p['wp']['id'], $only, true))->values();
        }

        $started = microtime(true);
        $bar = $this->output->createProgressBar($posts->count());
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% · %elapsed:6s% · nog ±%remaining:-6s%');

        foreach ($posts as $post) {
            // Photos are downloaded outside the transaction: a slow download shouldn't hold the database
            $content = $converter->convert($post['wp']['content']);
            $featured = $media && $post['wp']['thumbnail_id'] ? $media->import($post['wp']['thumbnail_id'], featured: true) : null;

            DB::transaction(fn () => $this->save($post, $trips, $author, $content, $featured, (bool) $media));
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Done: ' . Blog::whereNotNull('wordpress_id')->count() . ' blogs from WordPress in the database.');

        // Old links keep working: which WordPress link and photo became what (see WordPressRedirectController)
        if (! $only) {
            $this->writeRedirects($reader, $media);
        }

        $c = $this->comments->stats;
        $this->table(['Comments', ''], [
            ['Approved / waiting for approval', "{$c['approved']} / {$c['pending']}"],
            ['Your replies (under the comment they answer)', $c['replies']],
            ['Left behind (spam, trash' . (($this->config['pending_comments'] ?? true) ? '' : ', waiting') . ')', $c['skipped']],
        ]);

        if ($media) {
            $s = $media->stats;
            $mb = fn (int $bytes) => number_format($bytes / 1024 / 1024, 1, ',', '.') . ' MB';
            $this->table(['Photos', ''], [
                ['New / already there', "{$s['imported']} / {$s['reused']}"],
                ['Skipped (video, HEIC, …) / not found / failed', "{$s['skipped']} / {$s['missing']} / {$s['failed']}"],
                ['Downloaded (originals)', $mb($s['bytes_in'])],
                ['Stored (shrunk)', $mb($s['bytes_stored']) . ($s['bytes_in'] ? ' · ' . round(100 - $s['bytes_stored'] / $s['bytes_in'] * 100) . '% smaller' : '')],
                ['Time per new photo', $s['imported'] ? round((microtime(true) - $started) / $s['imported'], 1) . ' s' : '-'],
            ]);
            if ($s['failed']) {
                $this->warn('Failed photos are in storage/logs/laravel.log. Run the same command again to retry them.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * What one WordPress post becomes here, without touching the database yet.
     */
    private function plan(array $post): array
    {
        $terms = collect($post['categories'])->concat($post['tags']);
        $lookup = fn (array $map, string $name) => collect($map)->first(fn ($value, $key) => mb_strtolower($key) === mb_strtolower($name));
        $in = fn (array $list, string $name) => collect($list)->contains(fn ($item) => mb_strtolower($item) === mb_strtolower($name));

        // Cities from categories, tags and the title ("Berlijn" in a title counts too)
        $cities = collect($this->config['cities'])->keys()->filter(fn ($city) => $terms->contains(fn ($t) => mb_strtolower($t) === mb_strtolower($city))
            || preg_match('/\b' . preg_quote($city, '/') . '\b/iu', $post['title']));

        $countries = $terms->map(fn ($t) => $lookup($this->config['countries'], $t))
            ->concat($cities->map(fn ($city) => $this->config['cities'][$city]))
            ->filter()->unique()->values();

        // "Rookies op de terugreis [Lissabon, Dag 4]": the place leaves the title and goes on the map
        $place = TitlePlace::parse($post['title'], $this->knownCountries, $this->config['place_fixes'] ?? [], $countries->first());
        // 1. A fixed place from the config wins from everything ("Le Corbier, Frankrijk"),
        //    or "next trip": a warm-up blog that belongs to the journey after it
        $override = $this->config['place_overrides'][$post['slug']] ?? null;
        $followsNextTrip = $override === 'next trip';
        if ($override && ! $followsNextTrip) {
            $place['place'] = $override;
            $place['candidates'] = [['place' => $override, 'queries' => [$override]]];
        }

        // 2. No place in brackets: one named in the title itself ("*Taj Mahal*", "Een dagje Bremen", "Oui Oui dans Paris")
        if ($place['candidates'] === [] && ! $followsNextTrip && ($named = $this->placeInTitle($place['title'], $countries->first()))) {
            $place['place'] = $named;
            $place['candidates'] = [['place' => $named, 'queries' => array_values(array_unique([$named, explode(',', $named)[0]]))]];
        }

        // A country named with the place ("[Gerlos, Oostenrijk]", "Roosendaal, Nederland") counts when the blog had none
        if ($place['place'] && ($named = collect($this->knownCountries)->first(fn ($c) => str_ends_with($place['place'], ", {$c}") || $place['place'] === $c))) {
            if ($countries->isEmpty() || $override) {
                $countries = $countries->push($named)->unique()->values();
            }
        }

        $categories = collect($post['categories'])->map(fn ($c) => $lookup($this->config['categories'], $c))->filter()->unique()->values();

        $tags = collect($post['tags'])
            ->concat(collect($post['categories'])->filter(fn ($c) => $in($this->config['as_tags'], $c)))
            // Countries and cities are already countries; no need for a tag with the same name
            ->reject(fn ($t) => $lookup($this->config['countries'], $t) || $lookup($this->config['cities'], $t))
            ->unique(fn ($t) => mb_strtolower($t))->values();

        $unmapped = collect($post['categories'])->reject(fn ($c) => $lookup($this->config['countries'], $c) || $lookup($this->config['cities'], $c)
            || $lookup($this->config['categories'], $c) || $in($this->config['as_tags'], $c) || $in($this->config['ignore'], $c));

        $series = collect($this->config['series'])->first(fn ($name, $key) => $terms->contains(fn ($t) => mb_strtolower($t) === mb_strtolower($key)));

        $published = $post['status'] === 'publish';
        $date = $post['date_gmt'] && ! str_starts_with($post['date_gmt'], '0000') ? $post['date_gmt'] : $post['date'];

        return [
            'wp' => $post,
            'title' => $place['title'] ?: '(zonder titel)',
            'bracket' => $place['bracket'],
            'place' => $place['place'],
            'candidates' => $place['candidates'],
            'coordinates' => null,   // filled in by locate()
            'follows_next_trip' => $followsNextTrip,
            'slug' => $post['slug'] ?: Str::slug($post['title']) ?: 'wordpress-' . $post['id'],
            'status' => $published ? 'published' : 'draft',
            'published_at' => $published ? Carbon::parse($date, 'UTC') : null,
            'date' => Carbon::parse($date, 'UTC'),
            'excerpt' => $this->excerpt($post),
            'countries' => $countries,
            'categories' => $categories,
            'tags' => $tags,
            'unmapped' => $unmapped,
            // Where the post is: a city first, then a series (wintersport), then the country
            'destination' => $this->combined($cities->first() ?? $series ?? ($countries->count() === 1 ? $countries->first() : null), Carbon::parse($date)->year),
            'is_city' => $cities->isNotEmpty(),
        ];
    }

    /**
     * Series of posts about the same place, close together in time, become a trip.
     *
     * @return Collection<string, array{name: string, starts_on: Carbon, ends_on: Carbon, ids: array<int>}>
     */
    private function trips(Collection $posts): Collection
    {
        $trips = collect();

        $posts->filter(fn ($p) => $p['destination'] && $p['status'] === 'published')
            ->groupBy('destination')
            ->each(function (Collection $group, string $destination) use ($trips) {
                $run = [];
                $flush = function () use (&$run, $destination, $trips) {
                    $minimum = ($run[0]['is_city'] ?? false) ? $this->config['trip_min_posts_city'] : $this->config['trip_min_posts'];
                    if ($run && count($run) >= $minimum) {
                        $first = $run[0]['date'];
                        $name = "{$destination} {$first->year}";
                        if ($trips->has($name)) {
                            $name = "{$destination} " . $first->translatedFormat('F Y');
                        }
                        $trips->put($name, [
                            'name' => $name,
                            'starts_on' => $first,
                            'ends_on' => end($run)['date'],
                            'ids' => array_map(fn ($p) => $p['wp']['id'], $run),
                            // A trip around a city goes under "Citytrips" in the menu
                            'kind' => $run[0]['is_city'] ? Trip::KIND_CITY : Trip::KIND_TRIP,
                        ]);
                    }
                    $run = [];
                };

                foreach ($group->sortBy('date') as $post) {
                    if ($run && end($run)['date']->diffInDays($post['date']) > $this->config['trip_gap_days']) {
                        $flush();
                    }
                    $run[] = $post;
                }
                $flush();
            });

        return $trips->sortBy('starts_on');
    }

    private function save(array $post, Collection $trips, User $author, array $content, ?int $featured, bool $withPhotos): void
    {
        $blog = Blog::firstOrNew(['wordpress_id' => $post['wp']['id']]);
        $trip = $trips->first(fn ($t) => in_array($post['wp']['id'], $t['ids'], true));

        $blog->fill([
            'title' => $post['title'],
            'slug' => $this->freeSlug($post['slug'], $blog),
            // Set together, so the blog's own lookup (on a changed place) doesn't run again
            'location_name' => $post['place'],
            'latitude' => $post['coordinates'][0] ?? null,
            'longitude' => $post['coordinates'][1] ?? null,
            'excerpt' => $post['excerpt'],
            'content' => $content,
            'status' => $post['status'],
            'published_at' => $post['published_at'],
            'placed_by_id' => $author->id,
            'trip_id' => $trip
                ? Trip::firstOrCreate(['name' => $trip['name']], ['starts_on' => $trip['starts_on'], 'ends_on' => $trip['ends_on'], 'kind' => $trip['kind']])->id
                : null,
        ]);

        // Old blogs are not news: never mail the subscribers about them
        $blog->subscribers_notified_at ??= now();

        // Only touch the featured photo when photos are part of this run
        if ($withPhotos) {
            $blog->featured_image_id = $featured;
        }
        $blog->save();

        $blog->countries()->sync($post['countries']->map(fn ($name) => Country::firstOrCreate(['name' => $name])->id));
        $blog->categories()->sync($post['categories']->map(fn ($name) => Category::firstOrCreate(['name' => $name])->id));
        $blog->tags()->sync($post['tags']->map(function ($name) {
            return (Tag::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first() ?? Tag::create(['name' => $name]))->id;
        }));

        // The comments under it (matched on their WordPress id, so a second run updates them)
        $this->comments->import($blog, $post['wp']['comments']);
    }

    /**
     * Looks up the places from the titles on the map (OpenStreetMap, through the app's Geocoder).
     * Answers are remembered, so only the first run waits; Nominatim allows one request per second.
     * The outcome goes to storage/app/wordpress/places.csv, to check in a spreadsheet.
     */
    private function locate(Collection $posts): Collection
    {
        $todo = $posts->filter(fn ($p) => $p['candidates'] !== []);
        $lastRequest = 0.0;

        $this->info("Looking up {$todo->count()} places from the titles …");
        $bar = $this->output->createProgressBar($todo->count());

        $posts = $posts->map(function (array $post) use (&$lastRequest, $bar) {
            if ($post['candidates'] === []) {
                return $post;
            }

            // The places in the brackets in order: the first one the map knows wins
            foreach ($post['candidates'] as $candidate) {
                foreach ($candidate['queries'] as $query) {
                    if (! Cache::has('geocode:' . md5(mb_strtolower($query)))) {
                        // Nominatim's fair use: at most one request per second
                        $wait = 1.1 - (microtime(true) - $lastRequest);
                        if ($wait > 0) {
                            usleep((int) ($wait * 1_000_000));
                        }
                        $lastRequest = microtime(true);
                    }

                    if ($found = Geocoder::find($query)) {
                        $post['coordinates'] = $found;
                        $post['place'] = $candidate['place'];
                        break 2;
                    }
                }
            }

            $bar->advance();

            return $post;
        });

        $bar->finish();
        $this->newLine();

        return $posts;
    }

    /**
     * The map for the old links: WordPress slug → post id (the blog is found by that id, so it keeps
     * working when a link is changed in the admin later), and old photo path → new photo.
     * Written to storage/app/wordpress/redirects.json.
     */
    private function writeRedirects(WxrReader $reader, ?MediaImporter $media): void
    {
        $posts = collect($reader->posts)
            ->filter(fn ($p) => $p['slug'] !== '')
            ->mapWithKeys(fn ($p) => [mb_strtolower($p['slug']) => $p['id']]);

        // "2019/01/foto-04-01-19" for uploads/2019/01/Foto-04-01-19.jpg (and its -768x1024 and -scaled copies)
        $uploads = collect($media?->imported() ?? [])->mapWithKeys(function ($mediaId, $wordpressId) use ($reader) {
            $url = $reader->attachments[$wordpressId]['url'] ?? null;

            return $url ? [WordPressUploads::key($url) => $mediaId] : [];
        })->filter();

        file_put_contents(config('wordpress-import.directory') . '/redirects.json', json_encode([
            'posts' => $posts,
            'uploads' => $uploads,
        ]));

        $this->line("  Redirect map: {$posts->count()} old blog links, {$uploads->count()} old photo links.");
    }

    /**
     * Every post's place, to check in a spreadsheet: storage/app/wordpress/places.csv.
     */
    private function writePlaces(Collection $posts): void
    {
        $csv = fopen(config('wordpress-import.directory') . '/places.csv', 'w');
        fputcsv($csv, ['WordPress title', 'New title', 'Date', 'Place', 'Latitude', 'Longitude', 'Found'], ';', '"', '');
        foreach ($posts->sortBy('date') as $post) {
            fputcsv($csv, [
                $post['wp']['title'], $post['title'], $post['date']->format('Y-m-d'), $post['place'],
                $post['coordinates'][0] ?? '', $post['coordinates'][1] ?? '',
                match (true) {
                    isset($this->config['place_overrides'][$post['wp']['slug']]) && $post['coordinates'] && ! isset($post['from_trip']) => 'ja, vaste plaats (config)',
                    isset($post['travel_day']) => "ja, {$post['travel_day']} (van een blog ernaast)",
                    isset($post['from_trip']) => "ja, van de reis {$post['from_trip']}",
                    $post['coordinates'] !== null && $post['bracket'] === null => 'ja, uit de titel',
                    $post['coordinates'] !== null => 'ja',
                    $post['candidates'] !== [] => 'NEE',
                    $post['bracket'] !== null => 'geen plaats in titel',
                    default => 'geen [ ] in titel',
                },
            ], ';', '"', '');
        }
        fclose($csv);
    }

    /**
     * A place named in a title without brackets: text between *stars*, a word from 'title_places',
     * or one of the cities. Null when there is none.
     */
    private function placeInTitle(string $title, ?string $country): ?string
    {
        if (preg_match('/\*([^*]+)\*/u', $title, $m)) {
            return trim($m[1]) . ($country ? ", {$country}" : '');
        }

        foreach ($this->config['title_places'] ?? [] as $word => $place) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/iu', $title)) {
                return $place;
            }
        }

        foreach ($this->config['cities'] as $city => $cityCountry) {
            if (preg_match('/\b' . preg_quote($city, '/') . '\b/iu', $title)) {
                return "{$city}, {$cityCountry}";
            }
        }

        return null;
    }

    /**
     * Blogs in a trip that still have no place borrow one from the trip: before the first known
     * place they get that one (getting ready, the flight), after the last the last one (home again),
     * in between the place before them. A warm-up blog marked 'next trip' joins the trip after it first.
     */
    private function tripPlaces(Collection $posts, Collection $trips): array
    {
        // Warm-up blogs ("next trip") join the first trip that starts after them
        foreach ($posts->where('follows_next_trip', true) as $post) {
            $name = $trips->filter(fn ($t) => $t['starts_on']->greaterThan($post['date']))->sortBy('starts_on')->keys()->first();
            if ($name) {
                $trip = $trips->get($name);
                $trip['ids'][] = $post['wp']['id'];
                $trips->put($name, $trip);
            }
        }

        $byId = $posts->keyBy(fn ($p) => $p['wp']['id']);

        foreach ($trips as $trip) {
            $members = collect($trip['ids'])->map(fn ($id) => $byId[$id])->sortBy('date')->values();
            $known = $members->filter(fn ($p) => $p['coordinates'] !== null)->values();
            if ($known->isEmpty()) {
                continue;
            }

            foreach ($members as $i => $post) {
                if ($post['coordinates'] !== null) {
                    continue;
                }

                $source = $members->slice(0, $i)->reverse()->first(fn ($p) => $p['coordinates'] !== null) ?? $known->first();
                $post['coordinates'] = $source['coordinates'];
                $post['place'] = $source['place'];
                $post['from_trip'] = $trip['name'];
                $byId[$post['wp']['id']] = $post;
            }
        }

        return [$posts->map(fn ($p) => $byId[$p['wp']['id']]), $trips];
    }

    /**
     * Travel days have no place of their own: "[Heenreis, Peru]" belongs to the journey that follows
     * and gets its first place, "[Home sweet home]" or "[Terugreis]" the last place before it.
     * Only within one journey (no more than trip_gap_days apart).
     */
    private function travelDays(Collection $posts): Collection
    {
        $outbound = '/heenreis|vertrek|off to|op naar/iu';
        $return = '/terugreis|thuisreis|home sweet home|thuiskomen|terugblik|terug naar/iu';
        $gap = $this->config['trip_gap_days'];

        $dated = $posts->sortBy('date')->values();

        return $posts->map(function (array $post) use ($dated, $outbound, $return, $gap) {
            if ($post['coordinates']) {
                return $post;
            }

            $text = $post['bracket'] . ' ' . $post['wp']['title'];
            $direction = preg_match($outbound, $text) ? 'next' : (preg_match($return, $text) ? 'previous' : null);
            if (! $direction) {
                return $post;
            }

            $index = $dated->search(fn ($p) => $p['wp']['id'] === $post['wp']['id']);
            $neighbours = $direction === 'next' ? $dated->slice($index + 1) : $dated->slice(0, $index)->reverse();

            $source = $neighbours
                ->takeWhile(fn ($p) => abs($p['date']->diffInDays($post['date'])) <= $gap)
                ->first(fn ($p) => $p['coordinates'] !== null);

            if ($source) {
                $post['coordinates'] = $source['coordinates'];
                $post['place'] = $source['place'];
                // Part of that journey too, when it had no destination of its own
                $post['destination'] ??= $source['destination'];
                $post['is_city'] = $post['is_city'] || ($source['is_city'] && $post['destination'] === $source['destination']);
                $post['travel_day'] = $direction === 'next' ? 'heenreis' : 'terugreis';
            }

            return $post;
        });
    }

    // "Nepal" → "India & Nepal" when those were one journey (see 'combine' in the config).
    // A rule with a year ("Thailand 2017") wins from one without, and only counts for that year.
    private function combined(?string $destination, int $year): ?string
    {
        if ($destination === null) {
            return null;
        }

        $combine = collect($this->config['combine'] ?? [])->mapWithKeys(fn ($name, $key) => [mb_strtolower($key) => $name]);

        return $combine->get(mb_strtolower("{$destination} {$year}"))
            ?? $combine->get(mb_strtolower($destination))
            ?? $destination;
    }

    /**
     * The WordPress slug, unless another blog already has it (then "-2", "-3", …).
     */
    private function freeSlug(string $slug, Blog $blog): string
    {
        $candidate = $slug;
        for ($i = 2; Blog::where('slug', $candidate)->whereKeyNot($blog->getKey())->exists(); $i++) {
            $candidate = "{$slug}-{$i}";
        }

        return $candidate;
    }

    /**
     * The excerpt written in WordPress, otherwise the first sentences of the post.
     */
    private function excerpt(array $post): string
    {
        $text = $post['excerpt'] ?: $post['content'];
        $text = preg_replace(['#<!--.*?-->#s', '#\[/?[a-z_]+[^\]]*\]#i', '#<figcaption.*?</figcaption>#is'], ' ', $text);
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5)));

        return Str::limit($text, 220, '…', preserveWords: true) ?: $post['title'];
    }

    private function report(Collection $posts, Collection $trips, User $author, bool $dryRun): void
    {
        $existing = Blog::whereNotNull('wordpress_id')->pluck('wordpress_id');
        $new = $posts->reject(fn ($p) => $existing->contains($p['wp']['id']))->count();

        $this->newLine();
        $this->info($dryRun ? 'What the import would do' : 'Importing');
        $this->table(['', ''], [
            ['Blogs (new / update)', "{$new} / " . ($posts->count() - $new)],
            ['… published / draft', $posts->where('status', 'published')->count() . ' / ' . $posts->where('status', 'draft')->count()],
            ['Placed under', "{$author->name} ({$author->email})"],
            ['Countries', $posts->pluck('countries')->flatten()->countBy()->sortDesc()->map(fn ($n, $c) => "{$c} ({$n})")->join(', ')],
            ['Categories', $posts->pluck('categories')->flatten()->countBy()->sortDesc()->map(fn ($n, $c) => "{$c} ({$n})")->join(', ')],
            ['Tags', $posts->pluck('tags')->flatten()->unique(fn ($t) => mb_strtolower($t))->count() . ' different'],
            ['Blogs without a country', $posts->filter(fn ($p) => $p['countries']->isEmpty())->count()],
            ['Slugs already taken (get -2)', $posts->filter(fn ($p) => Blog::where('slug', $p['slug'])->where(fn ($q) => $q->whereNull('wordpress_id')->orWhere('wordpress_id', '!=', $p['wp']['id']))->exists())->count()],
        ]);

        $unmapped = $posts->pluck('unmapped')->flatten()->countBy();
        if ($unmapped->isNotEmpty()) {
            $this->warn('Categories not in config/wordpress-import.php (left out): ' . $unmapped->map(fn ($n, $c) => "{$c} ({$n})")->join(', '));
        }

        $this->newLine();
        $this->info($trips->count() . ' trips');
        $this->table(['Trip', 'From', 'To', 'Blogs'], $trips->map(fn ($t) => [
            $t['name'], $t['starts_on']->format('d-m-Y'), $t['ends_on']->format('d-m-Y'), count($t['ids']),
        ])->values()->all());

        // The content conversion, over all posts
        $converter = new ContentConverter();
        $stats = [];
        foreach ($posts as $post) {
            $converter->convert($post['wp']['content']);
            foreach ($converter->stats as $key => $count) {
                $stats[$key] = ($stats[$key] ?? 0) + $count;
            }
        }
        ksort($stats);
        $this->info('Content');
        $this->table(['What', 'Count'], collect($stats)->map(fn ($n, $k) => [$k, $n])->values()->all());
        $this->comment('"waiting:media" = photos, they come along with --photos.');

        $counter = new CommentImporter([...config('blog.admin_emails'), $author->email], $this->config['pending_comments'] ?? true);
        $posts->each(fn ($p) => $counter->count($p['wp']['comments']));
        $this->info('Comments');
        $this->table(['', ''], [
            ['Approved / waiting for approval', "{$counter->stats['approved']} / {$counter->stats['pending']}"],
            ['Your replies', $counter->stats['replies']],
            ['Left behind (spam, trash)', $counter->stats['skipped']],
        ]);
    }
}
