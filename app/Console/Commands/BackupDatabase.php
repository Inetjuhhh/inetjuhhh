<?php

namespace App\Console\Commands;

use App\Support\DatabaseDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Every night a copy of the database on the server itself (routes/console.php), kept for 14 days.
 * For getting back one blog or comment deleted by accident, without restoring the whole server from
 * Hetzner's backup. How to use a copy: UPDATESTEPS.md. Photos are not in it; Hetzner's backup has those.
 *
 *   php artisan site:backup   writes storage/app/backups/database-<date>.sql.gz
 */
class BackupDatabase extends Command
{
    protected $signature = 'site:backup {--keep=14 : How many days of copies to keep}';

    protected $description = 'Save a copy of the database (kept 14 days)';

    public const DIRECTORY = 'app/backups';

    public function handle(): int
    {
        $dir = storage_path(self::DIRECTORY);
        File::ensureDirectoryExists($dir);

        $file = $dir . '/database-' . now()->format('Y-m-d') . '.sql.gz';

        try {
            DatabaseDump::to($file, fn (string $warning) => $this->warn($warning));
        } catch (Throwable $e) {
            File::delete($file);
            report($e);
            $this->error('The copy failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        // Only the database's own copy may be read: it holds readers' email addresses
        chmod($file, 0600);

        $removed = self::removeOld($dir, (int) $this->option('keep'));
        $this->info(basename($file) . ': ' . number_format(filesize($file) / 1024 / 1024, 1) . " MB. Older copies removed: {$removed}");

        return self::SUCCESS;
    }

    /**
     * Removes the nightly copies older than $keep days; returns how many.
     */
    public static function removeOld(string $dir, int $keep): int
    {
        $limit = now()->subDays($keep)->format('Y-m-d');
        $removed = 0;

        // Only the nightly copies (database-YYYY-MM-DD.sql.gz); other files in the folder stay
        foreach (glob($dir . '/database-*.sql.gz') as $old) {
            if (preg_match('/database-(\d{4}-\d{2}-\d{2})\.sql\.gz$/', $old, $m) && $m[1] < $limit) {
                File::delete($old);
                $removed++;
            }
        }

        return $removed;
    }
}
