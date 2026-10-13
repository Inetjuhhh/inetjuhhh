<?php

namespace App\Support\WordPress;

use Closure;
use Filament\Forms\Components\RichEditor\RichContentRenderer;

/**
 * Turns the HTML of a WordPress post into this blog's editor format (TipTap JSON).
 *
 * Block posts (<!-- wp:… --> comments) are split into pieces: text goes through the editor's own
 * HTML converter, photos and galleries become photo and gallery blocks. Older "classic" posts
 * get their paragraphs from line breaks, and [gallery] / [caption] shortcodes are understood.
 *
 * Photos are handed to a resolver that returns this blog's media id (or null to leave them out).
 */
class ContentConverter
{
    /** @var array<string, int> What was found in the last post, for the import report */
    public array $stats = [];

    /**
     * @param  Closure(int $wordpressId): ?int|null  $resolveMedia
     */
    public function __construct(private ?Closure $resolveMedia = null)
    {
    }

    /**
     * @return array{type: 'doc', content: array<int, array<string, mixed>>}
     */
    public function convert(string $html): array
    {
        $this->stats = [];
        $pieces = str_contains($html, '<!-- wp:') ? $this->blocks($html) : $this->classic($html);

        $nodes = [];
        $text = '';

        $flushText = function () use (&$text, &$nodes) {
            if (trim(strip_tags($text, '<img>')) !== '') {
                $doc = (new RichContentRenderer($text))->toArray();
                array_push($nodes, ...($doc['content'] ?? []));
            }
            $text = '';
        };

        foreach ($pieces as $piece) {
            if ($piece['type'] === 'html') {
                $text .= $piece['html'];
                continue;
            }

            $flushText();
            if ($node = $this->mediaNode($piece)) {
                $nodes[] = $node;
            }
        }
        $flushText();

        return ['type' => 'doc', 'content' => $nodes];
    }

    // ---- Block posts ---------------------------------------------------------------------

    /**
     * The top-level blocks of a post, with nested blocks (columns, groups) opened up.
     */
    private function blocks(string $html): array
    {
        $pieces = [];

        foreach ($this->topLevelBlocks($html) as $block) {
            $name = $block['name'];
            $attrs = $block['attrs'];
            $inner = $block['inner'];
            $this->count("block:{$name}");

            switch ($name) {
                case 'image':
                    $id = $attrs['id'] ?? $this->firstImageId($inner);
                    $pieces[] = ['type' => 'photo', 'ids' => $id ? [$id] : [], 'caption' => $this->caption($inner)];
                    break;

                case 'gallery':
                case 'jetpack/tiled-gallery':
                    $ids = $attrs['ids'] ?? $this->imageIds($inner);
                    $pieces[] = ['type' => 'gallery', 'ids' => array_map('intval', $ids), 'caption' => $this->caption($inner, gallery: true)];
                    break;

                case 'video':
                    $pieces[] = ['type' => 'video', 'ids' => isset($attrs['id']) ? [(int) $attrs['id']] : []];
                    break;

                case 'embed':
                case 'core-embed/youtube':
                case 'core-embed/vimeo':
                    $pieces[] = ['type' => 'embed', 'url' => $attrs['url'] ?? trim(strip_tags($inner))];
                    break;

                // Layout blocks: their content counts, their layout doesn't
                case 'columns':
                case 'column':
                case 'group':
                case 'media-text':
                    array_push($pieces, ...$this->blocks($inner));
                    break;

                case 'freeform':
                case null:
                    array_push($pieces, ...$this->classic($inner));
                    break;

                default:
                    // paragraph, heading, list, quote, table, separator, … : plain HTML the editor understands
                    $pieces[] = ['type' => 'html', 'html' => $inner];
            }
        }

        return $pieces;
    }

    /**
     * Splits block HTML at its top-level <!-- wp:name {attrs} --> … <!-- /wp:name --> comments.
     * Text between blocks (old content pasted into a block post) comes back with name null.
     */
    private function topLevelBlocks(string $html): array
    {
        preg_match_all('#<!--\s+(/)?wp:([a-z0-9/-]+)(\s+(\{.*?\}))?\s+(/)?-->#s', $html, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $blocks = [];
        $depth = 0;
        $cursor = 0;
        $open = null;

        foreach ($tags as $tag) {
            [$full, $offset] = $tag[0];
            $closing = ($tag[1][0] ?? '') === '/';
            $selfClosing = ($tag[5][0] ?? '') === '/';
            $name = str_replace('core/', '', $tag[2][0]);

            if ($depth === 0 && ! $closing) {
                $between = substr($html, $cursor, $offset - $cursor);
                if (trim($between) !== '') {
                    $blocks[] = ['name' => null, 'attrs' => [], 'inner' => $between];
                }

                $attrs = json_decode($tag[4][0] ?? '{}', true) ?: [];

                if ($selfClosing) {
                    $blocks[] = ['name' => $name, 'attrs' => $attrs, 'inner' => ''];
                    $cursor = $offset + strlen($full);
                    continue;
                }

                $open = ['name' => $name, 'attrs' => $attrs, 'start' => $offset + strlen($full)];
            }

            if ($selfClosing) {
                continue;
            }

            $depth += $closing ? -1 : 1;

            if ($depth === 0 && $closing && $open) {
                $blocks[] = ['name' => $open['name'], 'attrs' => $open['attrs'], 'inner' => substr($html, $open['start'], $offset - $open['start'])];
                $cursor = $offset + strlen($full);
                $open = null;
            }
        }

        $rest = substr($html, $cursor);
        if (trim($rest) !== '') {
            $blocks[] = ['name' => null, 'attrs' => [], 'inner' => $rest];
        }

        return $blocks;
    }

    // ---- Classic posts (before the block editor) ------------------------------------------

    private function classic(string $html): array
    {
        $this->count('classic');
        $pieces = [];

        // [gallery ids="1,2,3"], [caption …]<img …> text[/caption], and photos on their own
        $pattern = '#\[gallery([^\]]*)\]|\[caption[^\]]*\](.*?)\[/caption\]|(<a[^>]*>\s*)?(<img[^>]*>)(\s*</a>)?|\[(?:embed|video)[^\]]*\](https?://[^\[]+)?(?:\[/(?:embed|video)\])?#is';

        $cursor = 0;
        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            [$full, $offset] = $match[0];
            $pieces[] = ['type' => 'html', 'html' => $this->autop(substr($html, $cursor, $offset - $cursor))];
            $cursor = $offset + strlen($full);

            if (str_starts_with($full, '[gallery')) {
                preg_match('/ids="([\d,\s]+)"/', $match[1][0], $ids);
                $pieces[] = ['type' => 'gallery', 'ids' => array_map('intval', array_filter(explode(',', $ids[1] ?? ''))), 'caption' => null];
                $this->count('shortcode:gallery');
            } elseif (str_starts_with($full, '[caption')) {
                $pieces[] = ['type' => 'photo', 'ids' => array_filter([$this->firstImageId($match[2][0])]), 'caption' => trim(strip_tags(preg_replace('#<img[^>]*>#', '', $match[2][0]))) ?: null];
                $this->count('shortcode:caption');
            } elseif (str_starts_with(ltrim($full), '[')) {
                $pieces[] = ['type' => 'embed', 'url' => trim($match[6][0] ?? '')];
            } else {
                $pieces[] = ['type' => 'photo', 'ids' => array_filter([$this->firstImageId($full)]), 'caption' => null];
            }
        }

        $pieces[] = ['type' => 'html', 'html' => $this->autop(substr($html, $cursor))];

        return $pieces;
    }

    /**
     * WordPress' own trick for classic posts: an empty line is a new paragraph, a single line break a <br>.
     */
    private function autop(string $html): string
    {
        $html = trim($html);
        if ($html === '' || preg_match('#<(p|h[1-6]|ul|ol|blockquote|table)\b#i', $html)) {
            return $html;
        }

        return collect(preg_split('/\n\s*\n/', str_replace("\r\n", "\n", $html)))
            ->map(fn ($part) => trim($part))
            ->filter()
            ->map(fn ($part) => '<p>' . nl2br($part, false) . '</p>')
            ->join('');
    }

    // ---- Photos, galleries and videos ---------------------------------------------------------

    private function mediaNode(array $piece): ?array
    {
        if ($piece['type'] === 'embed') {
            if (! preg_match('#youtu\.?be|vimeo#', $piece['url'] ?? '')) {
                $this->count('skipped:embed');

                return null;
            }
            $this->count('video:embed');

            return ['type' => 'customBlock', 'attrs' => ['id' => 'video', 'config' => ['url' => $piece['url']]]];
        }

        $ids = array_values(array_filter($piece['ids'] ?? []));
        $this->count($piece['type'], count($ids));

        if ($piece['type'] === 'video') {
            // Video files: decided in the photo step (they are large and play better from YouTube)
            $this->count('skipped:video-file');

            return null;
        }

        $media = $this->resolveMedia
            ? array_values(array_filter(array_map(fn (int $id) => ($this->resolveMedia)($id), $ids)))
            : [];

        if ($media === []) {
            $this->count('waiting:media', count($ids));

            return null;
        }

        $caption = $piece['caption'] ?? null;

        return $piece['type'] === 'gallery' && count($media) > 1
            ? ['type' => 'customBlock', 'attrs' => ['id' => 'gallery', 'config' => array_filter(['media' => $media, 'layout' => 'grid', 'caption' => $caption])]]
            : ['type' => 'customBlock', 'attrs' => ['id' => 'photo', 'config' => array_filter(['media' => $media[0], 'caption' => $caption])]];
    }

    private function firstImageId(string $html): ?int
    {
        return $this->imageIds($html)[0] ?? null;
    }

    /**
     * Photo ids from WordPress' class="wp-image-123" or data-id="123".
     *
     * @return list<int>
     */
    private function imageIds(string $html): array
    {
        preg_match_all('/(?:wp-image-|data-id=")(\d+)/', $html, $m);

        return array_values(array_unique(array_map('intval', $m[1])));
    }

    private function caption(string $html, bool $gallery = false): ?string
    {
        // A gallery's own caption has its own class; the other figcaptions belong to single photos
        $pattern = $gallery
            ? '#<figcaption[^>]*blocks-gallery-caption[^>]*>(.*?)</figcaption>#is'
            : '#<figcaption[^>]*>(.*?)</figcaption>#is';
        preg_match($pattern, $html, $m);
        $text = $m[1] ?? null;

        return $text ? trim(html_entity_decode(strip_tags($text))) ?: null : null;
    }

    private function count(string $key, int $by = 1): void
    {
        $this->stats[$key] = ($this->stats[$key] ?? 0) + $by;
    }
}
