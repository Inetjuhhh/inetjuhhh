<?php

namespace App\Support\WordPress;

use App\Support\ImageOptimizer;
use Awcodes\Curator\Models\Media;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Brings WordPress photos into the media library: downloads the original from the old site and
 * lets the media library shrink it (see ImageOptimizer). Which WordPress photo became which
 * media item is kept in a small file, so an interrupted import carries on where it stopped.
 */
class MediaImporter
{
    /** @var array<string, int> "123" or "featured:123" → media id */
    private array $map = [];

    /** @var array<string, int> */
    public array $stats = ['imported' => 0, 'reused' => 0, 'skipped' => 0, 'missing' => 0, 'failed' => 0, 'bytes_in' => 0, 'bytes_stored' => 0];

    public function __construct(
        private array $attachments,
        private string $mapFile,
        private array $skipTypes = [],
        // False: only photos imported before are used, nothing new is downloaded
        private bool $download = true,
    ) {
        if (is_file($mapFile)) {
            $this->map = json_decode(file_get_contents($mapFile), true) ?: [];
        }
    }

    /**
     * The media id for a WordPress photo, importing it the first time. Null when it can't come along.
     */
    public function import(int $wordpressId, bool $featured = false): ?int
    {
        $key = ($featured ? 'featured:' : '') . $wordpressId;

        if (isset($this->map[$key]) && Media::whereKey($this->map[$key])->exists()) {
            $this->stats['reused']++;

            return $this->map[$key];
        }

        if (! $this->download) {
            $this->stats['skipped']++;

            return null;
        }

        $attachment = $this->attachments[$wordpressId] ?? null;
        if (! $attachment) {
            $this->stats['missing']++;

            return null;
        }

        // Videos and other files stay behind; HEIC can't be read by the image library here
        if (in_array($attachment['mime'], [...$this->skipTypes, 'heic', 'heif'], true)) {
            $this->stats['skipped']++;

            return null;
        }

        try {
            $response = Http::timeout(120)->retry(3, 2000)->get($attachment['url']);
            $contents = $response->throw()->body();
            $size = @getimagesizefromstring($contents);
            if (! $size) {
                throw new \RuntimeException('not an image');
            }

            // Keep WordPress' year folders: uploads/2019/01/foto.jpg → wordpress/2019/foto.jpg
            preg_match('#/uploads/(\d{4})/#', $attachment['url'], $year);
            $directory = $featured ? ImageOptimizer::FEATURED_DIRECTORY : 'wordpress/' . ($year[1] ?? 'overig');
            $name = Str::slug(pathinfo(parse_url($attachment['url'], PHP_URL_PATH), PATHINFO_FILENAME)) ?: "foto-{$wordpressId}";
            $extension = image_type_to_extension($size[2], false) === 'jpeg' ? 'jpg' : image_type_to_extension($size[2], false);
            if (Storage::disk('public')->exists("{$directory}/{$name}.{$extension}") || Storage::disk('public')->exists("{$directory}/{$name}.webp")) {
                $name .= "-{$wordpressId}";
            }
            $path = "{$directory}/{$name}.{$extension}";
            Storage::disk('public')->put($path, $contents);

            // Saving shrinks it to the size budget and turns it into WebP (ImageOptimizer)
            $media = Media::create([
                'disk' => 'public',
                'directory' => $directory,
                'visibility' => 'public',
                'name' => $name,
                'path' => $path,
                'width' => $size[0],
                'height' => $size[1],
                'size' => strlen($contents),
                'type' => $size['mime'],
                'ext' => $extension,
                'title' => $attachment['title'] ?: null,
                'alt' => $attachment['alt'] ?: null,
            ]);
        } catch (Throwable $e) {
            $this->stats['failed']++;
            Log::warning("WordPress import: photo {$wordpressId} ({$attachment['url']}) failed: {$e->getMessage()}");

            return null;
        }

        $this->stats['imported']++;
        $this->stats['bytes_in'] += strlen($contents);
        $this->stats['bytes_stored'] += $media->size;

        $this->map[$key] = $media->id;
        // Written after every photo, so stopping halfway loses nothing
        file_put_contents($this->mapFile, json_encode($this->map));

        return $media->id;
    }
}
