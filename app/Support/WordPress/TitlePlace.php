<?php

namespace App\Support\WordPress;

/**
 * Old titles carry their place in brackets: "Rookies op de terugreis [Lissabon, Dag 4]".
 * This takes it out of the title and lists what to look up on the map: the places in the
 * brackets in their order (the first one that is found wins), with the country (from the
 * brackets, or else from the blog) to tell same-named places apart.
 */
class TitlePlace
{
    // Bracket parts that are not a place: a day or part number, the way there or back, home, a look back
    private const NOT_A_PLACE = '/^(dag|deel|day|part)?\s*\d+$|^(heenreis|terugreis|thuisreis|onderweg|home sweet home|thuis|vertrek|aankomst)$|^terugblik\b|^sam \d+ jaar$/iu';

    // Words around a place: "Off to Lissabon!" is Lissabon
    private const AROUND_A_PLACE = '/^(off to|op naar|naar|richting|welkom in)\s+|[!?.]+$/iu';

    /**
     * @param  list<string>  $countries  Known country names, to recognise "… - Spanje" and "…, Zweden"
     * @param  array<string, string>  $fixes  Title spelling → map spelling ("Snæfelsness" → "Snæfellsnes")
     * @param  string|null  $blogCountry  The blog's country, added when the brackets don't name one
     * @return array{title: string, bracket: ?string, place: ?string, candidates: list<array{place: string, queries: list<string>}>}
     */
    public static function parse(string $title, array $countries, array $fixes = [], ?string $blogCountry = null): array
    {
        if (! preg_match('/\s*\[([^\]]*)\]\s*/u', $title, $match)) {
            return ['title' => $title, 'bracket' => null, 'place' => null, 'candidates' => []];
        }

        // Not trim(): it works per byte and would cut "–" (three bytes) out of other characters
        $clean = preg_replace(['/\s+/u', '/^[\s\-–:|]+|[\s\-–:|]+$/u'], [' ', ''], str_replace($match[0], ' ', $title));
        // A title that was only the place ("[Cuzco]") becomes the place itself
        if ($clean === '') {
            $clean = preg_replace('/\s+[-–]\s+/u', ', ', trim($match[1]));
        }
        $bracket = trim($match[1]);

        // The parts: separated by , ; & / " - " and a hyphen before a capital ("Tambopata-Amazone", not "Salkantay-trail")
        $parts = collect(preg_split('/\s*(?:[,;&\/]|\s[-–]\s?|[-–](?=\p{Lu})|\s+en\s+)\s*/u', $bracket))
            ->map(fn ($p) => trim(preg_replace(self::AROUND_A_PLACE, '', trim($p))))
            ->reject(fn ($p) => $p === '' || preg_match(self::NOT_A_PLACE, $p));

        // A country among the parts ("Granada - Spanje", "Terugreis -Thailand") is the country, not a place.
        // Several countries are listed in the order of the places: the first place goes with the first
        // ("[Barcelona, Carcassonne - Spanje, Andorra, Frankrijk]" is Barcelona in Spain, not in France)
        $country = $parts->map(fn ($p) => self::isCountry($p, $countries))->filter()->first();
        $places = $parts->reject(fn ($p) => self::isCountry($p, $countries))->values();

        if ($places->isEmpty()) {
            // Not a place at all ("[1]", "[Sam 1 jaar]"): it stays part of the title, neatly,
            // so "De reis naar het onbekende [1]" to "[4]" don't all end up with the same title
            if (! $country && $bracket !== '') {
                $clean = preg_match('/^\d+$/', $bracket) ? "{$clean} (deel {$bracket})" : "{$clean} – {$bracket}";
            }

            // Just a country: no specific spot, the map uses the middle of the country
            return ['title' => $clean ?: $title, 'bracket' => $bracket, 'place' => $country, 'candidates' => []];
        }

        $country ??= $blogCountry;

        $candidates = $places->take(3)->map(function (string $place) use ($country, $fixes) {
            $fixed = collect($fixes)->first(fn ($to, $from) => mb_strtolower($from) === mb_strtolower($place)) ?? $place;
            // Looked up without "(Saigon)", shown with it
            $search = trim(preg_replace('/\s*\([^)]*\)/u', '', $fixed));

            return [
                'place' => $country ? "{$fixed}, {$country}" : $fixed,
                'queries' => array_values(array_unique(array_filter([$country ? "{$search}, {$country}" : null, $search]))),
            ];
        })->values()->all();

        return [
            'title' => $clean ?: $title,
            'bracket' => $bracket,
            // The first place, until the map lookup finds which one it knows
            'place' => $candidates[0]['place'],
            'candidates' => $candidates,
        ];
    }

    private static function isCountry(string $name, array $countries): ?string
    {
        foreach ($countries as $country) {
            if (mb_strtolower($country) === mb_strtolower(trim($name))) {
                return $country;
            }
        }

        return null;
    }
}
