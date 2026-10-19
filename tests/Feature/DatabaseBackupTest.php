<?php

use App\Console\Commands\BackupDatabase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;

it('keeps the copies of the last 14 days and leaves other files alone', function () {
    $dir = storage_path('framework/testing/backups');
    File::ensureDirectoryExists($dir);
    File::cleanDirectory($dir);

    foreach ([0, 13, 14, 15, 40] as $daysAgo) {
        touch($dir . '/database-' . now()->subDays($daysAgo)->format('Y-m-d') . '.sql.gz');
    }
    touch($dir . '/inetjuhhh-before-subjects.sql');   // a copy made by hand

    expect(BackupDatabase::removeOld($dir, 14))->toBe(2);

    $left = collect(File::files($dir))->map->getFilename()->sort()->values()->all();
    expect($left)->toHaveCount(4)->toContain('inetjuhhh-before-subjects.sql');

    File::deleteDirectory($dir);
});

it('makes the copy every night', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'site:backup'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('30 3 * * *');
});
