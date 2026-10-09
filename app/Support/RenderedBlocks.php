<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Filament sanitises rich content HTML, which would strip the iframes (maps, video) and
 * Alpine attributes (lightbox, slideshow) our own blocks need. Blocks therefore leave a
 * text placeholder in the content; after sanitising, the placeholders are swapped for
 * the block HTML. Only the block templates (which escape their input) bypass the sanitiser.
 */
class RenderedBlocks
{
    private string $token;

    /** @var array<int, string> */
    private array $blocks = [];

    public function __construct()
    {
        $this->token = Str::random(12);
    }

    public function put(string $html): string
    {
        $this->blocks[] = $html;

        return '<p>' . $this->placeholder(array_key_last($this->blocks)) . '</p>';
    }

    public function restore(string $html): string
    {
        foreach ($this->blocks as $index => $block) {
            $placeholder = $this->placeholder($index);
            $html = str_replace(["<p>{$placeholder}</p>", $placeholder], $block, $html);
        }

        return $html;
    }

    private function placeholder(int $index): string
    {
        // Letters and digits only, so the sanitiser leaves it untouched
        return "renderedblock{$this->token}n{$index}";
    }
}
