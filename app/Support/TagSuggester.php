<?php

namespace App\Support;

use Anthropic\Client;
use App\Models\Tag;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Asks Claude which of the existing subjects (tags) fit a blog, so a new story lands in the right
 * bubble on its category page. Claude can only answer with names from the list: the answer is a JSON
 * schema whose values are those names, so no new or misspelled tags come back.
 */
class TagSuggester
{
    // A few clear subjects read better as bubbles than many vague ones
    public const MAX_SUGGESTIONS = 4;

    public function isConfigured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * @param  list<string>  $categories  the categories chosen for the blog (Reizen, Lifestyle …)
     * @return list<string> names of existing tags
     */
    public function suggest(string $title, string $intro, string $text, array $categories = []): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Er is nog geen ANTHROPIC_API_KEY ingesteld in .env.');
        }

        $tags = $this->existingTags();
        if ($tags->isEmpty()) {
            return [];
        }

        $message = $this->client()->beta->messages->create(
            model: config('services.anthropic.model'),
            maxTokens: 4000,
            system: $this->instructions(),
            messages: [['role' => 'user', 'content' => $this->describe($tags, $title, $intro, $text, $categories)]],
            outputConfig: [
                // Picking from a list is a small task
                'effort' => 'low',
                'format' => [
                    'type' => 'json_schema',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'tags' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $tags->keys()->values()->all()]],
                        ],
                        'required' => ['tags'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            // When the model declines, the request is answered by another model in the same call
            betas: ['server-side-fallback-2026-07-01'],
            fallbacks: 'default',
        );

        if ($message->stopReason === 'refusal') {
            throw new RuntimeException('Claude wilde voor deze blog geen onderwerpen kiezen.');
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $answer = json_decode($block->text, true);

                return collect($answer['tags'] ?? [])
                    ->filter(fn ($name) => is_string($name) && $tags->has($name))
                    ->unique()
                    ->take(self::MAX_SUGGESTIONS)
                    ->values()
                    ->all();
            }
        }

        return [];
    }

    /**
     * Every tag with how many stories have it, the most used first.
     *
     * @return Collection<string, int> name → number of stories
     */
    public function existingTags(): Collection
    {
        return Tag::withCount('blogs')->orderByDesc('blogs_count')->orderBy('name')->get()
            ->mapWithKeys(fn (Tag $tag) => [$tag->name => (int) $tag->blogs_count]);
    }

    protected function client(): Client
    {
        return new Client(apiKey: config('services.anthropic.key'));
    }

    private function instructions(): string
    {
        return <<<'TEXT'
            Je helpt Ine met haar Nederlandstalige blog Inetjuhhh, over reizen met haar gezin (Bas en zoon Sam) en over haar leven thuis.
            Elke blog krijgt een paar onderwerpen. Op de categoriepagina's (Reizen, Lifestyle) worden die onderwerpen bubbels waarmee lezers verhalen vinden.

            Kies voor de blog hieronder de onderwerpen uit de bestaande lijst die er echt over gaan: meestal 1 tot 3, hooguit 4.
            - Kies alleen wat duidelijk in de tekst zit; liever minder dan een onderwerp dat er maar zijdelings in voorkomt.
            - Plaatsnamen en landen zijn geen onderwerpen: die staan al op de kaart.
            - "Met Sam op reis" past alleen bij een reis waar Sam bij is; "Sam" en "Familie" bij verhalen over het gezin thuis.
            - Past er niets, geef dan een lege lijst.
            TEXT;
    }

    /**
     * @param  Collection<string, int>  $tags
     */
    private function describe(Collection $tags, string $title, string $intro, string $text, array $categories): string
    {
        $list = $tags->map(fn (int $count, string $name) => "- {$name} ({$count} " . ($count === 1 ? 'verhaal' : 'verhalen') . ')')->join("\n");

        return implode("\n\n", array_filter([
            "Bestaande onderwerpen:\n{$list}",
            $categories ? 'Categorie van de blog: ' . implode(', ', $categories) : null,
            "Titel: {$title}",
            filled($intro) ? "Intro: {$intro}" : null,
            filled($text) ? "Verhaal:\n{$text}" : null,
        ]));
    }
}
