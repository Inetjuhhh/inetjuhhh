<?php

use Awcodes\Curator\Models\Media;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The blog content was written with the awcodes TipTap plugin (Filament 3). Filament's own
 * rich editor uses the same TipTap JSON, except for a few nodes:
 *
 * - gridBuilder / gridBuilderColumn  -> grid / gridColumn
 * - tiptapBlock (galleryBlock)       -> customBlock "gallery"; its photos are added to the media library
 * - textStyle marks (text colours picked for the old white site) are dropped
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('blogs')->whereNotNull('content')->orderBy('id')->each(function ($blog) {
            $content = json_decode($blog->content, true);

            if (! is_array($content)) {
                return;
            }

            $converted = $this->convert($content);

            if ($converted !== $content) {
                DB::table('blogs')->where('id', $blog->id)->update(['content' => json_encode($converted)]);
            }
        });
    }

    public function down(): void
    {
        // Not reversible; restore the database backup made before the upgrade if needed.
    }

    private function convert(array $node): array
    {
        switch ($node['type'] ?? null) {
            case 'gridBuilder':
                $node = [
                    'type' => 'grid',
                    'attrs' => [
                        'data-cols' => (string) ($node['attrs']['data-cols'] ?? count($node['content'] ?? []) ?: 2),
                        'data-from-breakpoint' => $node['attrs']['data-stack-at'] ?? 'md',
                    ],
                    'content' => $node['content'] ?? [],
                ];
                break;

            case 'gridBuilderColumn':
                $node = [
                    'type' => 'gridColumn',
                    'attrs' => ['data-col-span' => (string) ($node['attrs']['data-col-span'] ?? 1)],
                    'content' => $node['content'] ?? [],
                ];
                break;

            case 'tiptapBlock':
                return $this->convertBlock($node);
        }

        if (isset($node['marks'])) {
            $node['marks'] = array_values(array_filter($node['marks'], fn ($mark) => ($mark['type'] ?? null) !== 'textStyle'));

            if ($node['marks'] === []) {
                unset($node['marks']);
            }
        }

        if (isset($node['content']) && is_array($node['content'])) {
            $node['content'] = array_map(fn ($child) => $this->convert($child), $node['content']);
        }

        return $node;
    }

    private function convertBlock(array $node): array
    {
        $type = $node['attrs']['type'] ?? null;
        $data = $node['attrs']['data'] ?? [];

        if ($type !== 'galleryBlock') {
            // Unknown block from the old editor: keep it visible as a note instead of losing it silently
            return [
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => "[Blok uit de oude editor: {$type}]"]],
            ];
        }

        $paths = $data['gallery_images'] ?? [];
        $mediaIds = array_values(array_filter(array_map(fn ($path) => $this->importIntoLibrary($path), $paths)));
        $missing = array_values(array_diff($paths, array_keys($this->imported)));

        return [
            'type' => 'customBlock',
            'attrs' => [
                'id' => 'gallery',
                'config' => array_filter([
                    'media' => $mediaIds,
                    'layout' => ! empty($data['slideshow']) ? 'slideshow' : 'grid',
                    'caption' => $data['title'] ?? null,
                    // Files that could not be imported stay referenced by path
                    'gallery_images' => $missing ?: null,
                ], fn ($value) => $value !== null),
            ],
        ];
    }

    /** @var array<string, int> */
    private array $imported = [];

    private function importIntoLibrary(string $path): ?int
    {
        if (isset($this->imported[$path])) {
            return $this->imported[$path];
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $existing = Media::where('disk', 'public')->where('path', $path)->value('id');

        if ($existing) {
            return $this->imported[$path] = $existing;
        }

        [$width, $height] = @getimagesize($disk->path($path)) ?: [null, null];
        $info = pathinfo($path);

        $media = new Media();
        $media->forceFill([
            'disk' => 'public',
            // Shown in the main folder of the media library; the file itself stays where it is
            'directory' => null,
            'visibility' => 'public',
            'name' => $info['filename'],
            'path' => $path,
            'width' => $width,
            'height' => $height,
            'size' => $disk->size($path),
            'type' => $disk->mimeType($path) ?: 'image/jpeg',
            'ext' => strtolower($info['extension'] ?? 'jpg'),
        ])->save();

        return $this->imported[$path] = $media->id;
    }
};
