<?php

namespace App\Console\Commands;

use App\Support\PhotoFingerprint;
use Awcodes\Curator\Models\Media;
use Illuminate\Console\Command;

/**
 * Gives photos in the media library a fingerprint, so an upload of the same photo is noticed.
 * New uploads get one by themselves; this is for the photos that were there before. Safe to stop and run again.
 */
class FingerprintMedia extends Command
{
    protected $signature = 'media:fingerprint {--all : Also photos that already have one}';

    protected $description = 'Give photos in the media library a fingerprint to find duplicates';

    public function handle(): int
    {
        $query = Media::query()->where('type', 'like', 'image/%')
            ->unless($this->option('all'), fn ($query) => $query->whereNull('fingerprint'));

        $bar = $this->output->createProgressBar($query->count());
        $failed = 0;

        $query->chunkById(200, function ($photos) use ($bar, &$failed) {
            foreach ($photos as $media) {
                $fingerprint = PhotoFingerprint::forMedia($media);
                // Not one of Curator's fillable columns, so set directly
                $fingerprint === null ? $failed++ : $media->forceFill(['fingerprint' => $fingerprint])->saveQuietly();
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Done.' . ($failed ? " {$failed} could not be read (missing file or not a photo)." : ''));

        return self::SUCCESS;
    }
}
