<?php

namespace App\Support;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Country;
use App\Models\Tag;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Finding blogs for the search bar. Every word typed has to appear somewhere: in the title,
 * intro, place, story text, or in a country, trip, category or tag the blog belongs to.
 * Title matches come first, then the newest.
 */
class BlogSearch
{
    private const MAX_TERMS = 6;

    /**
     * The words to look for: lower case, without punctuation, at least 2 characters.
     *
     * @return list<string>
     */
    public static function terms(string $query): array
    {
        $words = preg_split('/[^\p{L}\p{N}\'-]+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY);

        return array_slice(array_values(array_unique(array_filter(
            array_map(fn (string $word) => trim($word, "'-"), $words),
            fn (string $word) => mb_strlen($word) >= 2,
        ))), 0, self::MAX_TERMS);
    }

    public static function query(string $query): Builder
    {
        $terms = static::terms($query);
        $builder = Blog::published();

        if ($terms === []) {
            return $builder->whereRaw('1 = 0');
        }

        foreach ($terms as $term) {
            $like = static::like($term);

            $builder->where(fn (Builder $where) => $where
                ->where('title', 'like', $like)
                ->orWhere('excerpt', 'like', $like)
                ->orWhere('location_name', 'like', $like)
                ->orWhere('search_text', 'like', $like)
                ->orWhereHas('countries', fn (Builder $q) => $q->where('name', 'like', $like))
                ->orWhereHas('trip', fn (Builder $q) => $q->where('name', 'like', $like))
                ->orWhereHas('categories', fn (Builder $q) => $q->where('name', 'like', $like))
                ->orWhereHas('tags', fn (Builder $q) => $q->where('name', 'like', $like)));
        }

        $whole = static::like(implode(' ', $terms));
        $first = static::like($terms[0]);

        return $builder
            ->orderByRaw(
                'CASE WHEN title LIKE ? THEN 0 WHEN title LIKE ? THEN 1 WHEN location_name LIKE ? OR excerpt LIKE ? THEN 2 ELSE 3 END',
                [$whole, $first, $first, $first],
            )
            ->newestFirst();
    }

    /**
     * Countries and trips whose name matches, as quick links above the stories.
     *
     * @return Collection<int, array{label: string, url: string, type: string}>
     */
    public static function places(string $query, int $limit = 3): Collection
    {
        $terms = static::terms($query);

        if ($terms === []) {
            return collect();
        }

        $matches = fn (Builder $q) => collect($terms)->each(fn ($term) => $q->where('name', 'like', static::like($term)));

        $trips = Trip::whereHas('blogs', fn ($q) => $q->published())->where($matches)
            ->orderByDesc('starts_on')->limit($limit)->get()
            ->map(fn (Trip $trip) => ['label' => $trip->name, 'url' => $trip->filterUrl(), 'type' => 'trip']);

        $countries = Country::whereHas('blogs', fn ($q) => $q->published())->where($matches)
            ->orderBy('name')->limit($limit)->get()
            ->map(fn (Country $country) => ['label' => $country->name, 'url' => route('blogs.blogCountry', $country->id), 'type' => 'country']);

        return $countries->concat($trips)->take($limit)->values();
    }

    /**
     * A corrected query for small typos ("tailand" → "thailand"), from the words that actually
     * occur on the blog: countries, trips, places, categories, tags and title words.
     */
    public static function didYouMean(string $query): ?string
    {
        $terms = static::terms($query);
        $vocabulary = static::vocabulary();
        $changed = false;

        $corrected = array_map(function (string $term) use ($vocabulary, &$changed) {
            if (mb_strlen($term) < 4 || isset($vocabulary[$term])) {
                return $term;
            }

            $best = null;
            $bestDistance = PHP_INT_MAX;
            // One typo per four letters: "tailand" (7) may be off by one
            $allowed = max(1, intdiv(mb_strlen($term), 4));

            foreach ($vocabulary as $word => $_) {
                if (abs(mb_strlen($word) - mb_strlen($term)) > $allowed) {
                    continue;
                }

                $distance = levenshtein($term, $word);
                if ($distance <= $allowed && $distance < $bestDistance) {
                    [$best, $bestDistance] = [$word, $distance];
                }
            }

            $changed = $changed || $best !== null;

            return $best ?? $term;
        }, $terms);

        return $changed ? implode(' ', $corrected) : null;
    }

    /**
     * Plain text of the editor content, for searching: the words of paragraphs and headings,
     * plus the texts in blocks (tips, captions, practical info), without links or file names.
     */
    public static function plainText(mixed $content): string
    {
        $text = '';

        $walk = function (mixed $node, bool $inConfig = false) use (&$walk, &$text) {
            if (! is_array($node)) {
                return;
            }

            foreach ($node as $key => $value) {
                // Bold, italic and links hold no text of their own
                if ($key === 'marks') {
                    continue;
                }

                if (is_array($value)) {
                    $walk($value, $inConfig || $key === 'config');
                } elseif ($key === 'text' && is_string($value)) {
                    // Pieces of one paragraph (bold, links) are glued together as written
                    $text .= $value;
                } elseif ($inConfig && is_string($value) && ! preg_match('~[/\\\\]|^https?:|^\d+$|^[a-z]+$~', $value)) {
                    $text .= ' ' . strip_tags($value) . ' ';
                }
            }

            // A paragraph, heading or block ends with a space, so words of different blocks don't merge
            if (isset($node['type']) && $node['type'] !== 'text') {
                $text .= ' ';
            }
        };

        $walk(is_array($content) ? $content : []);

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $text)), 0, 60000);
    }

    /**
     * @return array<string, true>
     */
    private static function vocabulary(): array
    {
        $names = collect()
            ->concat(Country::pluck('name'))
            ->concat(Trip::pluck('name'))
            ->concat(Category::pluck('name'))
            ->concat(Tag::pluck('name'))
            ->concat(Blog::published()->pluck('location_name'))
            ->concat(Blog::published()->pluck('title'));

        return $names->filter()
            ->flatMap(fn (string $name) => static::terms($name))
            ->filter(fn (string $word) => mb_strlen($word) >= 4)
            ->unique()
            ->mapWithKeys(fn (string $word) => [$word => true])
            ->all();
    }

    private static function like(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }
}
