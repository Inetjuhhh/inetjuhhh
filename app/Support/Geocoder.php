<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Looks up coordinates for a place name ("Ghorepani, Nepal") with OpenStreetMap's free
 * Nominatim service. Results are cached, so each place is only looked up once; Nominatim
 * asks for at most one request per second, which an admin saving blogs never comes near.
 */
class Geocoder
{
    /**
     * @return array{0: float, 1: float}|null  [latitude, longitude], or null when not found
     */
    public static function find(string $place): ?array
    {
        $place = trim($place);

        if ($place === '') {
            return null;
        }

        $key = 'geocode:' . md5(mb_strtolower($place));

        if (Cache::has($key)) {
            return Cache::get($key);
        }

        try {
            $result = Http::timeout(8)
                ->withHeaders(['User-Agent' => config('app.name') . ' travel blog (' . config('app.url') . ')'])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $place,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'accept-language' => 'nl',
                ])
                ->throw()
                ->json('0');
        } catch (Throwable) {
            // Service unreachable: try again on the next save instead of caching a miss
            return null;
        }

        $coordinates = $result ? [(float) $result['lat'], (float) $result['lon']] : null;
        Cache::forever($key, $coordinates);

        return $coordinates;
    }

    // Country names OpenStreetMap uses where the countries list of the blog has its own
    private const COUNTRY_ALIASES = [
        'Verenigd Koninkrijk' => ['Groot-Brittannië', 'Engeland'],
        'Verenigde Staten van Amerika' => ['Verenigde Staten'],
        'Tsjechië' => ['Tsjechische Republiek'],
    ];

    /**
     * A place written the same way every time, "Cefalù, Italië": the place as OpenStreetMap names it,
     * and the country in Dutch, so the blog shows "Italië – Cefalù" (Blog::placeTags) however it was
     * typed ("cefalu", "Cefalu Sicilië", "Italië, Cefalù"). Only the country for a country ("Peru").
     *
     * @return string|null  null when the place isn't found
     */
    public static function placeName(string $place): ?string
    {
        $place = trim($place);

        if ($place === '') {
            return null;
        }

        $key = 'geoplace:' . md5(mb_strtolower($place));

        if (Cache::has($key)) {
            return Cache::get($key);
        }

        try {
            $result = Http::timeout(8)
                ->withHeaders(['User-Agent' => config('app.name') . ' travel blog (' . config('app.url') . ')'])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $place,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'addressdetails' => 1,
                    'namedetails' => 1,
                    'accept-language' => 'nl',
                ])
                ->throw()
                ->json('0');
        } catch (Throwable) {
            return null;
        }

        $name = null;

        if ($result && filled($country = $result['address']['country'] ?? null)) {
            $country = static::knownCountry($country);
            $typed = static::withoutCountries($place, $country);
            $local = static::localName($result);

            // Your own name for the place, unless OpenStreetMap writes that same name more neatly
            // ("cefalu" → "Cefalù"); its own names can be an area ("Groot-Londen" for "London")
            $chosen = match (true) {
                ($result['addresstype'] ?? null) === 'country' && $typed === '' => null,
                $local !== null && ($typed === '' || static::sameName($typed, $local)) => $local,
                default => $typed !== '' ? $typed : null,
            };

            $name = filled($chosen) && mb_strtolower($chosen) !== mb_strtolower($country) ? "{$chosen}, {$country}" : $country;
        }

        Cache::forever($key, $name);

        return $name;
    }

    /**
     * What was typed, without the country names in it: "Italië, Cefalù" → "Cefalù", "Cefalu Sicilië" stays.
     */
    private static function withoutCountries(string $place, string $country): string
    {
        $countries = \App\Models\Country::pluck('name')->push($country)->map(fn ($name) => mb_strtolower($name));

        return collect(explode(',', $place))
            ->map(fn ($part) => trim($part))
            ->reject(fn ($part) => $part === '' || $countries->contains(mb_strtolower($part)))
            ->join(', ');
    }

    /**
     * The same name, apart from capitals, accents, dashes and a word extra: "cefalu" and "Cefalù",
     * "Monte Carlo" and "Monte-Carlo", "Cefalu Sicilië" and "Cefalù". Not "London" and "Groot-Londen".
     */
    private static function sameName(string $typed, string $osm): bool
    {
        $plain = fn (string $text) => preg_replace('/[^a-z0-9]+/', ' ', strtolower(\Illuminate\Support\Str::ascii($text)));
        [$a, $b] = [trim($plain($typed)), trim($plain($osm))];

        if ($a === '' || $b === '') {
            return false;
        }

        // The place's own name at the start of what was typed: "cefalu sicilie" starts with "cefalu"
        if (str_starts_with($a . ' ', $b . ' ') || $a === $b) {
            return true;
        }

        similar_text(str_replace(' ', '', $a), str_replace(' ', '', $b), $percent);

        return $percent >= 85;
    }

    /**
     * The name of the place itself according to OpenStreetMap, in Dutch where it has one, else in
     * Latin letters; null when it only has a name in another script.
     */
    private static function localName(array $result): ?string
    {
        if (($result['addresstype'] ?? null) === 'country') {
            return null;
        }

        $names = $result['namedetails'] ?? [];

        foreach ([$names['name:nl'] ?? null, $result['name'] ?? null, $names['name:en'] ?? null] as $name) {
            if (filled($name) && preg_match('/\p{Latin}/u', $name)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * The country as the blog's countries list has it, when that list has it under another name.
     */
    private static function knownCountry(string $country): string
    {
        $known = \App\Models\Country::pluck('name');

        if ($known->contains($country)) {
            return $country;
        }

        foreach (self::COUNTRY_ALIASES[$country] ?? [] as $alias) {
            if ($known->contains($alias)) {
                return $alias;
            }
        }

        return $country;
    }

    /**
     * The rectangle a country lies in, to spot a pin that landed in the wrong country.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}|null  [south, north, west, east]
     */
    public static function countryBox(string $country): ?array
    {
        $key = 'geocode:box:' . md5(mb_strtolower($country));

        if (Cache::has($key)) {
            return Cache::get($key);
        }

        try {
            $result = Http::timeout(8)
                ->withHeaders(['User-Agent' => config('app.name') . ' travel blog (' . config('app.url') . ')'])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'country' => $country,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'accept-language' => 'nl',
                ])
                ->throw()
                ->json('0.boundingbox');
        } catch (Throwable) {
            return null;
        }

        $box = $result ? array_map('floatval', $result) : null;
        Cache::forever($key, $box);

        return $box;
    }
}
