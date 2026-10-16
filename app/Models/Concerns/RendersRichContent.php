<?php

namespace App\Models\Concerns;

use App\Filament\Blocks\BlogBlock;
use App\Support\BlogImages;
use App\Support\RenderedBlocks;
use Filament\Forms\Components\RichEditor\RichContentRenderer;

/**
 * For models with editor content in a `content` column (blogs, fixed pages).
 */
trait RendersRichContent
{
    /**
     * The content as safe HTML, including the editor blocks (galleries, maps, tips, ...).
     */
    public function renderContent(): string
    {
        if (blank($this->content)) {
            return '';
        }

        $stash = new RenderedBlocks();

        $html = RichContentRenderer::make($this->content)
            ->customBlocks(collect(BlogBlock::all())->mapWithKeys(fn ($block) => [$block => ['stash' => $stash]])->all())
            ->toHtml();

        // Serve images in the text resized as WebP instead of the original upload
        $html = preg_replace_callback(
            '~(<img\b[^>]*?\bsrc=")(?:https?://[^/"]+)?/storage/([^"]+\.(?:jpe?g|png|webp))(")~i',
            fn (array $m) => $m[1] . e(BlogImages::url(rawurldecode($m[2]))) . $m[3],
            $html,
        );

        return $stash->restore($html);
    }
}
