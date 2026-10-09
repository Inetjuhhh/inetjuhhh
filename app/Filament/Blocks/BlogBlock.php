<?php

namespace App\Filament\Blocks;

use App\Support\RenderedBlocks;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;

abstract class BlogBlock extends RichContentCustomBlock
{
    /**
     * All blocks available in the blog editor, in the order they appear in the block panel.
     *
     * @return array<class-string<BlogBlock>>
     */
    public static function all(): array
    {
        return [
            PhotoBlock::class,
            GalleryBlock::class,
            VideoBlock::class,
            MapBlock::class,
            TipBlock::class,
            PracticalInfoBlock::class,
        ];
    }

    /**
     * HTML for the website.
     */
    abstract protected static function render(array $config): string;

    public static function toHtml(array $config, array $data): ?string
    {
        $html = static::render($config);

        // On the website the HTML is stashed and restored after sanitising, see RenderedBlocks
        return ($data['stash'] ?? null) instanceof RenderedBlocks
            ? $data['stash']->put($html)
            : $html;
    }
}
