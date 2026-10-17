<?php

namespace App\Console\Commands;

use App\Models\Blog;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Packs the site for the production server: the database (without sessions, cache and queued jobs)
 * and the photo library. Git has the code; these two files have everything else.
 *
 *   php artisan site:export   writes storage/app/export/inetjuhhh-database.sql.gz and inetjuhhh-photos.tar
 */
class ExportSite extends Command
{
    protected $signature = 'site:export {--force : Export even when test content is still there}';

    protected $description = 'Export the database and the photos for the production server';

    // Tables whose rows only matter on this computer: they are exported empty
    private const EMPTY_TABLES = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];

    public function handle(): int
    {
        if (! $this->option('force') && $this->hasTestContent()) {
            return self::FAILURE;
        }

        $dir = storage_path('app/export');
        File::ensureDirectoryExists($dir);

        $this->exportDatabase("{$dir}/inetjuhhh-database.sql.gz");
        $this->exportPhotos("{$dir}/inetjuhhh-photos.tar");

        $this->newLine();
        $this->table(['What', 'Count'], [
            ['Blogs', Blog::count() . ' (' . Blog::where('status', 'published')->count() . ' published)'],
            ['Photos in the library', DB::table('curator')->count()],
            ['Comments', DB::table('responses')->count()],
            ['Trips', Trip::count()],
            ['Old links', DB::table('legacy_links')->count()],
            ['Admin accounts', User::pluck('email')->join(', ')],
        ]);
        $this->info("Done. Both files are in {$dir}");

        return self::SUCCESS;
    }

    private function hasTestContent(): bool
    {
        $found = array_filter([
            'test trips' => Trip::where('description', 'like', 'TESTREIS%')->pluck('name')->join(', '),
            'test blogs' => Blog::where('title', 'like', 'Test:%')->pluck('title')->join(', '),
            'test accounts' => User::where('email', 'like', '%@example.%')->pluck('email')->join(', '),
        ]);

        foreach ($found as $what => $names) {
            $this->warn("Still in the database ({$what}): {$names}");
        }

        if ($found) {
            $this->error('Remove these first (see PRODUCTIONSTEPS.md, step 1), or add --force to export anyway.');
        }

        return (bool) $found;
    }

    private function exportDatabase(string $file): void
    {
        $this->info('Database …');
        $db = config('database.connections.mysql');
        $base = [
            $this->mysqldump(), '--host=' . $db['host'], '--port=' . $db['port'], '--user=' . $db['username'],
            '--single-transaction', '--no-tablespaces', '--set-gtid-purged=OFF', '--default-character-set=utf8mb4',
        ];
        $env = filled($db['password']) ? ['MYSQL_PWD' => $db['password']] : [];

        $gz = gzopen($file, 'wb6');

        // First every table's structure, then the rows of all tables except the local-only ones
        $runs = [
            [...$base, '--no-data', '--routines', $db['database']],
            [...$base, '--no-create-info', '--skip-triggers',
                ...array_map(fn ($table) => "--ignore-table={$db['database']}.{$table}", self::EMPTY_TABLES),
                $db['database']],
        ];

        foreach ($runs as $command) {
            Process::env($env)->timeout(600)
                ->run($command, fn (string $type, string $output) => $type === 'out' ? gzwrite($gz, $output) : $this->warn(trim($output)))
                ->throw();
        }

        gzclose($gz);
        $this->line('  ' . $this->size($file));
    }

    private function exportPhotos(string $file): void
    {
        $this->info('Photos (this takes a minute) …');

        // Only the files; tar keeps the folders, so it unpacks straight into storage/app/public.
        // A relative path, because some tar versions read "C:" as the name of another computer.
        Process::timeout(1800)->path(storage_path('app/public'))
            ->run(['tar', '-cf', '../export/' . basename($file), '--exclude=.gitignore', '.'])
            ->throw();

        $this->line('  ' . $this->size($file));
    }

    private function mysqldump(): string
    {
        if ($path = env('MYSQLDUMP_PATH')) {
            return $path;
        }

        // Laragon keeps MySQL in its own folder, not on the PATH
        $laragon = glob('C:/laragon/bin/mysql/*/bin/mysqldump.exe') ?: [];

        return end($laragon) ?: 'mysqldump';
    }

    private function size(string $file): string
    {
        return basename($file) . ': ' . number_format(filesize($file) / 1024 / 1024, 1) . ' MB';
    }
}
