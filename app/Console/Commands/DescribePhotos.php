<?php

namespace App\Console\Commands;

use Anthropic\Beta\Messages\Batches\MessageBatchSucceededResult;
use App\Models\Setting;
use App\Support\AltTextWriter;
use Awcodes\Curator\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Lets Claude write the description (alt text) of the photos that don't have one yet.
 *
 *   php artisan media:describe --dry-run      only count, and estimate how much goes to Claude
 *   php artisan media:describe --limit=10     ten photos one by one, to look at the result first
 *   php artisan media:describe --batch        all of them as batches: half the price, ready within 24 hours
 *   php artisan media:describe --collect      save the descriptions of finished batches (the scheduler
 *                                             does this every 15 minutes by itself)
 *
 * One by one takes about half a minute per photo, so for hundreds of photos the batch is the way.
 * Every description is saved as soon as it is there; running it again only does the photos still
 * without one. Photos that already have a description (also ones written by hand) are never touched.
 */
class DescribePhotos extends Command
{
    protected $signature = 'media:describe
        {--dry-run : Only count and estimate, send nothing}
        {--limit= : At most this many photos}
        {--batch : Send them as batches (half the price, ready within 24 hours)}
        {--collect : Save the descriptions of finished batches}';

    protected $description = 'Let Claude describe the photos without a description (alt text)';

    // The batches that were sent and not collected yet
    public const PENDING = 'alt-text-batches';

    // Photos per batch: a batch may be at most 256 MB, and each photo travels along as a JPG
    private const PER_BATCH = 100;

    // Roughly what one photo costs in tokens besides the photo itself: instructions, blog, answer
    private const TEXT_TOKENS = 400;

    private const ANSWER_TOKENS = 250;

    public function handle(AltTextWriter $writer): int
    {
        if (! $this->option('dry-run') && ! $writer->isConfigured()) {
            $this->error('Er is nog geen ANTHROPIC_API_KEY ingesteld in .env.');

            return self::FAILURE;
        }

        if ($this->option('collect')) {
            return $this->collect($writer);
        }

        $photos = $this->photosWithoutDescription();

        if ($photos->isEmpty()) {
            $this->info('Alle foto\'s hebben al een beschrijving' . (Setting::get(self::PENDING) ? ', of zitten in een batch die nog loopt.' : '.'));

            return self::SUCCESS;
        }

        $this->estimate($photos);

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        if (! $this->confirm('Starten?', true)) {
            return self::SUCCESS;
        }

        return $this->option('batch') ? $this->sendBatches($writer, $photos) : $this->oneByOne($writer, $photos);
    }

    /**
     * Photos without a description, leaving out those already waiting in a batch.
     *
     * @return Collection<int, Media>
     */
    private function photosWithoutDescription(): Collection
    {
        $waiting = collect(Setting::get(self::PENDING, []))->flatMap(fn (array $batch) => $batch['media'])->all();

        $query = Media::query()
            ->where('type', 'like', 'image/%')
            ->where('type', '!=', 'image/svg+xml')
            ->where(fn ($query) => $query->whereNull('alt')->orWhere('alt', ''))
            ->whereNotIn('id', $waiting)
            ->orderBy('id');

        if ($limit = (int) $this->option('limit')) {
            $query->limit($limit);
        }

        return $query->get();
    }

    private function estimate(Collection $photos): void
    {
        $input = $photos->sum(fn (Media $photo) => $this->imageTokens($photo) + self::TEXT_TOKENS);
        $output = $photos->count() * self::ANSWER_TOKENS;
        $millions = fn (int $tokens) => number_format($tokens / 1_000_000, 2, ',', '.');

        $this->line("Foto's zonder beschrijving: <info>{$photos->count()}</info>");
        $this->line("Geschat: {$millions($input)} miljoen tokens erin en {$millions($output)} miljoen eruit, met model " . config('services.anthropic.alt_model') . '.');
        $this->line('Reken het om met de prijzen op anthropic.com/pricing (per miljoen tokens); met --batch betaal je de helft.');
        $this->line('Je tegoed zie je op console.anthropic.com.');
    }

    private function oneByOne(AltTextWriter $writer, Collection $photos): int
    {
        $done = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar($photos->count());

        foreach ($photos as $photo) {
            try {
                $photo->update(['alt' => $writer->describe($photo)]);
                $done++;
            } catch (Throwable $e) {
                // One photo that doesn't work (unreadable, refused) mustn't stop the rest
                $failed++;
                $this->newLine();
                $this->warn("Foto {$photo->id} ({$photo->name}): {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Klaar: {$done} beschreven" . ($failed ? ", {$failed} overgeslagen (draai het nog eens om die opnieuw te proberen)" : '') . '.');

        return self::SUCCESS;
    }

    private function sendBatches(AltTextWriter $writer, Collection $photos): int
    {
        $client = $writer->client();
        $pending = Setting::get(self::PENDING, []);
        $bar = $this->output->createProgressBar($photos->count());

        foreach ($photos->chunk(self::PER_BATCH) as $chunk) {
            $requests = [];
            $ids = [];

            foreach ($chunk as $photo) {
                try {
                    // Batches don't support the server-side fallback: a declined photo is simply skipped there
                    $requests[] = ['customID' => "media-{$photo->id}", 'params' => array_diff_key($writer->request($photo), ['fallbacks' => true])];
                    $ids[] = $photo->id;
                } catch (Throwable $e) {
                    $this->newLine();
                    $this->warn("Foto {$photo->id} ({$photo->name}): {$e->getMessage()}");
                }
                $bar->advance();
            }

            if ($requests === []) {
                continue;
            }

            $batch = $client->beta->messages->batches->create(requests: $requests);

            // Saved after every batch, so a stop halfway never loses one that was already sent
            $pending[] = ['id' => $batch->id, 'media' => $ids];
            Setting::put(self::PENDING, $pending);
        }

        $bar->finish();
        $this->newLine(2);
        $this->info(count($pending) . ' batch(es) onderweg. De beschrijvingen komen vanzelf binnen (de planner kijkt elk kwartier),');
        $this->info('meestal binnen een uur, uiterlijk binnen 24 uur. Zelf kijken: php artisan media:describe --collect');

        return self::SUCCESS;
    }

    private function collect(AltTextWriter $writer): int
    {
        $pending = Setting::get(self::PENDING, []);

        if ($pending === []) {
            $this->info('Er lopen geen batches.');

            return self::SUCCESS;
        }

        $client = $writer->client();
        $saved = 0;
        $failed = 0;
        $stillRunning = [];

        foreach ($pending as $batch) {
            $status = $client->beta->messages->batches->retrieve($batch['id']);

            if ($status->processingStatus !== 'ended') {
                $stillRunning[] = $batch;

                continue;
            }

            foreach ($client->beta->messages->batches->resultsStream($batch['id']) as $response) {
                $photo = Media::find((int) str_replace('media-', '', $response->customID));

                try {
                    if (! $response->result instanceof MessageBatchSucceededResult) {
                        throw new \RuntimeException("Claude kon deze foto niet beschrijven ({$response->result->type}).");
                    }

                    // Never overwrite a description written in the meantime
                    if ($photo && blank($photo->alt)) {
                        $photo->update(['alt' => $writer->altFrom($response->result->message)]);
                        $saved++;
                    }
                } catch (Throwable $e) {
                    $failed++;
                    $this->warn("Foto {$response->customID}: {$e->getMessage()}");
                }
            }
        }

        Setting::put(self::PENDING, $stillRunning ?: null);

        $this->info("Opgeslagen: {$saved}" . ($failed ? ", niet gelukt: {$failed} (die gaan mee met de volgende --batch)" : '')
            . ($stillRunning ? '. Nog bezig: ' . count($stillRunning) . ' batch(es).' : '.'));

        return self::SUCCESS;
    }

    private function imageTokens(Media $photo): int
    {
        $width = $photo->width ?: 1024;
        $height = $photo->height ?: 768;
        $scale = min(1, 1024 / max($width, $height));

        return (int) ceil(($width * $scale) * ($height * $scale) / 750);
    }
}
