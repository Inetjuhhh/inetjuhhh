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
}
