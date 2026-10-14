<?php

namespace App\Console\Commands;

use App\Models\Blog;
use Illuminate\Console\Command;

/**
 * Places corrected in the admin survive the next import (on production too).
 *
 * Compares every imported blog's place and pin with what the import made of it (places.csv, from
 * the last run) and saves the differences to database/wordpress/place-corrections.json. That file
 * is part of the project: commit it, and the import uses it everywhere, before any other rule.
 */
class KeepWordPressPlaces extends Command
{
    protected $signature = 'wordpress:keep-places';

    protected $description = 'Save places corrected in the admin, so the WordPress import keeps them';

    public static function path(): string
    {
        return config('wordpress-import.corrections_file');
    }

    /**
     * @return array<int, array{slug: string, title: string, place: ?string, latitude: ?float, longitude: ?float}>
     */
    public static function load(): array
    {
        return is_file(static::path()) ? (json_decode(file_get_contents(static::path()), true) ?: []) : [];
    }

    public function handle(): int
    {
        $csv = config('wordpress-import.directory') . '/places.csv';
        if (! is_file($csv)) {
            $this->error('No places.csv yet: run php artisan wordpress:import --dry-run first.');

            return self::FAILURE;
        }

        // What the import made of each blog, by WordPress id
        $imported = collect(file($csv, FILE_IGNORE_NEW_LINES))->skip(1)
            ->map(fn ($line) => str_getcsv($line, ';', '"', ''))
            ->keyBy(fn ($row) => (int) $row[0]);

        $corrections = static::load();
        $found = [];

        foreach (Blog::whereNotNull('wordpress_id')->get() as $blog) {
            $row = $imported->get($blog->wordpress_id);
            if (! $row) {
                continue;
            }

            // Columns: WordPress id, WordPress title, new title, date, place, latitude, longitude, found
            $samePlace = (string) $blog->location_name === (string) $row[4];
            $samePin = $this->same($blog->latitude, $row[5]) && $this->same($blog->longitude, $row[6]);

            if (! $samePlace || ! $samePin) {
                $corrections[$blog->wordpress_id] = $found[] = [
                    'slug' => $blog->slug,
                    'title' => $blog->title,
                    'place' => $blog->location_name ?: null,
                    'latitude' => $blog->latitude,
                    'longitude' => $blog->longitude,
                ];
            }
        }

        if (! is_dir(dirname(static::path()))) {
            mkdir(dirname(static::path()), 0755, true);
        }
        ksort($corrections);
        file_put_contents(static::path(), json_encode($corrections, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

        if ($found === []) {
            $this->info('No new corrections: every place is as the import made it.');
        } else {
            $this->info(count($found) . ' corrections saved:');
            $this->table(['Blog', 'Place', 'Pin'], collect($found)->map(fn ($c) => [
                $c['title'], $c['place'] ?? '(no place)', $c['latitude'] !== null ? round($c['latitude'], 4) . ', ' . round($c['longitude'], 4) : '-',
            ])->all());
        }

        $this->comment(count($corrections) . ' corrections in total in database/wordpress/place-corrections.json. Commit that file to keep them.');

        return self::SUCCESS;
    }

    // Pins are the same when they differ less than ~10 metres
    private function same(?float $a, string $b): bool
    {
        if ($a === null || $b === '') {
            return $a === null && $b === '';
        }

        return abs($a - (float) $b) < 0.0001;
    }
}
