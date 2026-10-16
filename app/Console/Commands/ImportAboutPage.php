<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Support\WordPress\ContentConverter;
use App\Support\WordPress\MediaImporter;
use App\Support\WordPress\WxrReader;
use Illuminate\Console\Command;

/**
 * Fills the "Ik & wij" page with the WordPress page "Over mij en ons": the text, and of its many
 * photos only the first row (Ine: wedding day, the llama and Cusco) as one gallery.
 *
 *   php artisan wordpress:import-about           fills the page, if it has no text yet
 *   php artisan wordpress:import-about --force   overwrites what was written in the admin
 */
class ImportAboutPage extends Command
{
    protected $signature = 'wordpress:import-about
        {file? : The export file (default: the newest .xml in storage/app/wordpress)}
        {--force : Also when the page already has text}';

    protected $description = 'Fill the "Ik & wij" page from the WordPress page "Over mij en ons"';

    private const WORDPRESS_SLUG = 'over-ine';

    // The text ends with this line; the photo albums below it stay behind
    private const TEXT_ENDS_WITH = 'Liefs, Ine</p>';

    private const PHOTOS_TO_KEEP = 3;

    public function handle(): int
    {
        $page = Page::about();
        if (filled($page->content) && ! $this->option('force')) {
            $this->warn('"Ik & wij" already has text; nothing changed. Use --force to overwrite it.');

            return self::SUCCESS;
        }

        $file = $this->argument('file') ?? collect(glob(config('wordpress-import.directory') . '/*.xml'))->sortDesc()->first();
        if (! $file) {
            $this->error('No export file found in storage/app/wordpress.');

            return self::FAILURE;
        }

        $reader = new WxrReader($file);
        $wordpress = $reader->pages[self::WORDPRESS_SLUG] ?? null;
        if (! $wordpress) {
            $this->error('The page "' . self::WORDPRESS_SLUG . '" is not in ' . basename($file) . '.');

            return self::FAILURE;
        }

        $html = $wordpress['content'];
        $end = strpos($html, self::TEXT_ENDS_WITH);
        $text = $end === false ? $html : substr($html, 0, $end + strlen(self::TEXT_ENDS_WITH));
        $photos = array_slice($this->photoIds(substr($html, strlen($text))), 0, self::PHOTOS_TO_KEEP);

        $media = new MediaImporter($reader->attachments, config('wordpress-import.directory') . '/media-map.json', config('wordpress-import.skip_media'));
        $content = (new ContentConverter(fn (int $id) => $media->import($id)))
            ->convert($text . ($photos ? "\n\n[gallery ids=\"" . implode(',', $photos) . '"]' : ''));

        $page->update(['content' => $content]);

        $this->info('"Ik & wij" filled: ' . count($content['content']) . ' parts, ' . ($media->stats['imported'] + $media->stats['reused']) . ' photos.');
        $this->line('  The text is from 2019–2022 (age, Sam\'s age, trips): update it in the admin under "Ik & wij".');

        return self::SUCCESS;
    }

    /**
     * WordPress photo ids in the order they appear (class="wp-image-123").
     *
     * @return list<int>
     */
    private function photoIds(string $html): array
    {
        preg_match_all('/wp-image-(\d+)/', $html, $m);

        return array_values(array_unique(array_map('intval', $m[1])));
    }
}
