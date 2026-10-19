<?php

namespace App\Console\Commands;

use App\Support\DatabaseDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The backup outside Hetzner, every night (routes/console.php): a fresh copy of the database
 * (the last 30 days are kept there) and the photos that aren't there yet. Photos removed from the
 * site stay in the backup, so a photo deleted by accident can always come back.
 *
 *   php artisan site:offsite-backup                    makes the backup now
 *   php artisan site:offsite-backup --restore-photos   puts photos missing on the server back from the backup
 *
 * The storage is set in .env (OFFSITE_…, config/filesystems.php "offsite"); without it, nothing happens.
 */
class OffsiteBackup extends Command
{
    protected $signature = 'site:offsite-backup {--restore-photos : Download the photos that are missing on this server}';

    protected $description = 'Copy the database and the photos to the storage outside Hetzner';

    public const DISK = 'offsite';

    public const KEEP_DAYS = 30;

    public static function isConfigured(): bool
    {
        return filled(config('filesystems.disks.offsite.bucket'));
    }

    public function handle(): int
    {
        if (! static::isConfigured()) {
            $this->info('Geen opslag buiten Hetzner ingesteld (OFFSITE_BUCKET in .env): niets gedaan.');

            return self::SUCCESS;
        }

        if ($this->option('restore-photos')) {
            return $this->restorePhotos();
        }

        try {
            $this->database();
            $this->photos();
        } catch (Throwable $e) {
            // Reported, so the error mail (ErrorAlert) tells Ine the backup didn't work
            report($e);
            $this->error('De backup buiten Hetzner is niet gelukt: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function database(): void
    {
        $remote = Storage::disk(self::DISK);
        $local = storage_path('app/offsite-database.sql.gz');

        try {
            DatabaseDump::to($local);
            $stream = fopen($local, 'r');
            $remote->writeStream('database/database-' . now()->format('Y-m-d') . '.sql.gz', $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        } finally {
            File::delete($local);
        }

        // Older copies than 30 days go; the newest ones always stay
        $limit = now()->subDays(self::KEEP_DAYS)->format('Y-m-d');
        $removed = 0;
        foreach ($remote->files('database') as $file) {
            if (preg_match('/database-(\d{4}-\d{2}-\d{2})\.sql\.gz$/', $file, $m) && $m[1] < $limit) {
                $remote->delete($file);
                $removed++;
            }
        }

        $this->info('Database: gekopieerd' . ($removed ? ", {$removed} oude kopie(ën) weggegooid" : '') . '.');
    }

    private function photos(): void
    {
        $local = Storage::disk('public');
        $remote = Storage::disk(self::DISK);

        // What is there already: one list, instead of asking for every photo separately
        $there = array_flip(array_map(fn (string $path) => substr($path, strlen('photos/')), $remote->allFiles('photos')));

        $sent = 0;
        foreach ($local->allFiles() as $path) {
            if (basename($path) === '.gitignore' || isset($there[$path])) {
                continue;
            }

            $stream = $local->readStream($path);
            $remote->writeStream("photos/{$path}", $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            $sent++;
        }

        $this->info("Foto's: {$sent} nieuw gekopieerd, " . count($there) . ' stonden er al.');
    }

    private function restorePhotos(): int
    {
        $local = Storage::disk('public');
        $remote = Storage::disk(self::DISK);
        $restored = 0;

        foreach ($remote->allFiles('photos') as $remotePath) {
            $path = substr($remotePath, strlen('photos/'));

            // Only what is missing: a photo that is still there is never overwritten
            if ($local->exists($path)) {
                continue;
            }

            $stream = $remote->readStream($remotePath);
            $local->writeStream($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            $restored++;
        }

        $this->info("Teruggezet: {$restored} foto('s). Foto's die er nog waren, zijn niet aangeraakt.");

        return self::SUCCESS;
    }
}
