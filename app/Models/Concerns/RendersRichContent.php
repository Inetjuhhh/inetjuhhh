<?php

namespace App\Models\Concerns;

use App\Filament\Blocks\BlogBlock;
use App\Support\BlogImages;
use App\Support\RenderedBlocks;
use Awcodes\Curator\Models\Media;
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

        // Serve images in the text resized as WebP instead of the original upload, in the sizes a screen needs
        $pattern = '~(<img\b[^>]*?\bsrc=")(?:https?://[^/"]+)?/storage/([^"]+\.(?:jpe?g|png|webp))(")~i';
        preg_match_all($pattern, $html, $found);
        $library = $found[2] ? Media::whereIn('path', array_map('rawurldecode', $found[2]))->get()->keyBy('path') : collect();

        $html = preg_replace_callback($pattern, function (array $m) use ($library) {
            $path = rawurldecode($m[2]);

            if (! $media = $library->get($path)) {
                return $m[1] . e(BlogImages::url($path)) . $m[3];
            }

            return $m[1] . e(BlogImages::mediaUrl($media)) . $m[3]
                . ' srcset="' . e(BlogImages::srcset($media)) . '" sizes="(min-width: 768px) 768px, 100vw"';
        }, $html);

        return $stash->restore($html);
    }
}
