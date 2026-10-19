<?php

namespace App\Console\Commands;

use App\Models\Blog;
use App\Models\Trip;
use App\Models\User;
use App\Support\DatabaseDump;
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
        DatabaseDump::to($file, fn (string $warning) => $this->warn($warning));
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

    private function size(string $file): string
    {
        return basename($file) . ': ' . number_format(filesize($file) / 1024 / 1024, 1) . ' MB';
    }
}
