<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use MaxMind\Db\Reader;
use Throwable;

/**
 * The country and (roughly) the city of a reader, looked up from the address in a file on our own
 * server (DB-IP "IP to City Lite", CC BY 4.0, updated monthly by `stats:update-locations`). No outside
 * service is asked, and the address is not kept. Cities are an estimate: mobile networks often give a
 * big city nearby. Without the file, nothing is known and readers are still counted.
 */
class ReaderLocation
{
    public const PATH = 'app/geoip/dbip-city-lite.mmdb';

    private static ?Reader $reader = null;

    /**
     * @return array{country: string, city: string} empty strings when unknown
     */
    public static function lookup(?string $ip): array
    {
        $unknown = ['country' => '', 'city' => ''];

        if (blank($ip) || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $unknown;
        }

        try {
            $record = static::reader()?->get($ip);
        } catch (Throwable $e) {
            Log::warning("Reader location lookup failed: {$e->getMessage()}");

            return $unknown;
        }

        $city = (string) ($record['city']['names']['nl'] ?? $record['city']['names']['en'] ?? '');

        return [
            'country' => strtoupper((string) ($record['country']['iso_code'] ?? '')),
            // "Mechelen (Arrondissement of Mechelen)" → "Mechelen"
            'city' => mb_substr(trim(preg_replace('/\s*\(.*\)$/', '', $city)), 0, 100),
        ];
    }

    public static function available(): bool
    {
        return is_file(storage_path(self::PATH));
    }

    /**
     * Dutch name of a country code: "NL" → "Nederland".
     */
    public static function countryName(string $code): string
    {
        if ($code === '') {
            return 'Onbekend';
        }

        $name = class_exists(\Locale::class) ? \Locale::getDisplayRegion('-' . $code, 'nl') : '';

        return $name !== '' && $name !== $code ? $name : $code;
    }

    /**
     * Forgets the open file (after an update, and in tests).
     */
    public static function reset(): void
    {
        static::$reader?->close();
        static::$reader = null;
    }

    private static function reader(): ?Reader
    {
        if (static::$reader === null && static::available()) {
            static::$reader = new Reader(storage_path(self::PATH));
        }

        return static::$reader;
    }
}
