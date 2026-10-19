<?php

namespace App\Support;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Client;
use App\Models\Blog;
use Awcodes\Curator\Models\Media;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Asks Claude for a short Dutch description of a photo (its alt text): what a screen reader reads
 * aloud for someone who can't see it, and what search engines use to understand the photo.
 * Claude looks at a smaller copy of the photo, plus the blog it is in, so "Pompeï" or "de
 * Etna" can be named. One photo at a time from the admin ("Beschrijf met AI"), or many at once
 * as a batch (DescribePhotos): the question is the same.
 */
class AltTextWriter
{
    // Shorter than this reads well aloud; screen readers cut long ones short
    public const MAX_LENGTH = 150;

    // Server-side fallback: when the model declines, another model answers in the same call
    public const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    // Claude sees the photo at most this big: enough to recognise it, and cheaper than the original
    private const LONG_SIDE = 1024;

    private const INSTRUCTIONS = <<<'TEXT'
        Je schrijft de alt-tekst voor een foto op Inetjuhhh, de Nederlandstalige blog van Ine over reizen met haar man Bas en zoon Sam, en over het gezinsleven thuis.
        Een alt-tekst wordt voorgelezen aan mensen die de foto niet kunnen zien, en helpt zoekmachines.

        Regels:
        - Eén korte zin in het Nederlands van hooguit 20 woorden. Begin niet met "Foto van", "Afbeelding van" of "Selfie van".
        - Beschrijf wat er echt te zien is: wie of wat, waar, wat er gebeurt. Het belangrijkste eerst.
        - Gebruik de plek uit de blog als die duidelijk bij de foto past ("bij de Etna", "in Pompeï"). Verzin geen plek.
        - Noem mensen niet bij naam: je weet niet wie wie is. Schrijf "een vrouw", "een jongetje", "drie mensen".
        - Geen meningen of mooie woorden ("prachtig", "gezellig"): alleen wat je ziet.
        TEXT;

    /** @var array<int, Blog>|null Which blog each photo is in (built once, for many photos in a row) */
    private ?array $blogs = null;

    public function isConfigured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    public function describe(Media $media): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Er is nog geen ANTHROPIC_API_KEY ingesteld in .env.');
        }

        return $this->altFrom($this->client()->beta->messages->create(...$this->request($media), betas: [self::FALLBACK_BETA]));
    }

    /**
     * The question for one photo, as the parameters of a message: the same for a single request
     * and for a batch.
     */
    public function request(Media $media): array
    {
        return [
            'model' => config('services.anthropic.alt_model'),
            'maxTokens' => 2000,
            'system' => self::INSTRUCTIONS,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => 'image/jpeg', 'data' => base64_encode($this->smallCopy($media))]],
                    ['type' => 'text', 'text' => $this->context($media)],
                ],
            ]],
            'outputConfig' => [
                // Describing one photo is a small task
                'effort' => 'low',
                'format' => [
                    'type' => 'json_schema',
                    'schema' => [
                        'type' => 'object',
                        'properties' => ['alt' => ['type' => 'string']],
                        'required' => ['alt'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'fallbacks' => 'default',
        ];
    }

    /**
     * The description from Claude's answer.
     *
     * @throws RuntimeException when Claude declined or gave none
     */
    public function altFrom(BetaMessage $message): string
    {
        if ($message->stopReason === 'refusal') {
            throw new RuntimeException('Claude wilde deze foto niet beschrijven.');
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $alt = trim((string) (json_decode($block->text, true)['alt'] ?? ''));

                if ($alt !== '') {
                    return static::shorten($alt);
                }
            }
        }

        throw new RuntimeException('Claude gaf geen beschrijving terug.');
    }

    /**
     * Too long to read aloud comfortably: cut after the last comma or whole word that fits, so no
     * half word or "…" is read aloud.
     */
    public static function shorten(string $alt): string
    {
        if (mb_strlen($alt) <= self::MAX_LENGTH) {
            return $alt;
        }

        $cut = mb_substr($alt, 0, self::MAX_LENGTH);
        $comma = mb_strrpos($cut, ',');
        $cut = $comma !== false && $comma > self::MAX_LENGTH / 2 ? mb_substr($cut, 0, $comma) : mb_substr($cut, 0, (int) mb_strrpos($cut, ' '));

        return rtrim($cut, ' ,;:-') . '.';
    }

    public function client(): Client
    {
        return new Client(apiKey: config('services.anthropic.key'));
    }

    /**
     * The photo as a JPG of at most 1024 pixels on its longest side.
     */
    private function smallCopy(Media $media): string
    {
        $contents = Storage::disk($media->disk)->get($media->path);
        $image = $contents ? @imagecreatefromstring($contents) : false;

        if (! $image) {
            throw new RuntimeException('Deze foto kan niet worden gelezen.');
        }

        $scale = min(1, self::LONG_SIDE / max(imagesx($image), imagesy($image)));
        if ($scale < 1) {
            $image = imagescale($image, (int) round(imagesx($image) * $scale), (int) round(imagesy($image) * $scale));
        }

        ob_start();
        imagejpeg($image, null, 82);

        return ob_get_clean();
    }

    private function context(Media $media): string
    {
        $this->blogs ??= PhotoBlogs::index();
        $blog = $this->blogs[$media->getKey()] ?? null;

        if (! $blog) {
            return 'Deze foto staat (nog) niet in een blog.';
        }

        $place = collect($blog->placeTags())->pluck('label')->join(', ');

        return "Deze foto staat in de blog \"{$blog->title}\"" . ($place ? " (plek: {$place})" : '') . '.';
    }
}
