<?php

namespace App\Filament\Forms;

use App\Filament\Blocks\BlogBlock;
use Awcodes\Curator\Components\Forms\RichEditor\AttachCuratorMediaPlugin;
use Filament\Forms\Components\RichEditor;

/**
 * The text editor of a blog, with photos from the media library and the blog blocks.
 * Also used for fixed pages like "Ik & wij", so they can hold the same things.
 */
class StoryEditor
{
    public static function make(string $name = 'content'): RichEditor
    {
        return RichEditor::make($name)
            ->json()
            ->toolbarButtons([
                ['bold', 'italic', 'link'],
                ['h2', 'h3'],
                ['blockquote', 'bulletList', 'orderedList', 'horizontalRule'],
                [InsertPhotosAction::NAME, 'customBlocks', 'grid', 'table'],
                ['undo', 'redo'],
            ])
            ->floatingToolbars([
                'paragraph' => ['bold', 'italic', 'link', 'h2', 'h3'],
                'heading' => ['h2', 'h3'],
                'grid' => ['gridAddColumnBefore', 'gridAddColumnAfter', 'gridDeleteColumn', 'gridDelete'],
                'table' => ['tableAddColumnAfter', 'tableDeleteColumn', 'tableAddRowAfter', 'tableDeleteRow', 'tableDelete'],
            ])
            // Photos are added with our own button (one photo, several, or a gallery); Curator's plugin
            // stays for photos that are already loose in older texts
            ->plugins([AttachCuratorMediaPlugin::make()])
            ->tools([InsertPhotosAction::tool()])
            ->registerActions([InsertPhotosAction::make()])
            ->customBlocks(BlogBlock::all())
            ->searchableCustomBlocks(false)
            ->stickyToolbar()
            ->resizableImages()
            ->extraInputAttributes(['style' => 'min-height: 28rem']);
    }
}
