<?php
namespace App\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use FilamentTiptapEditor\Extensions\Nodes\Grid;
use FilamentTiptapEditor\TiptapBlock;

class GalleryBlock extends TiptapBlock
{
    public string $preview = "tiptapblocks.preview.gallery";
    public string $rendered = "tiptapblocks.rendered.gallery";
    public string $category = 'General';
    public string $width = 'xl';
    public bool $slideOver = true;
    public ?string $icon = 'heroicon-o-photo';

    public function getLabel(): string
    {
        return 'Gallery';
    }

    public function getFormSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Titel')
                ->placeholder('Titel van de galerij')
                ->required(),
            Toggle::make('slideshow')
                ->label('Slideshow')
                ->default(false),
            FileUpload::make('gallery_images')
            ->label('Gallery afbeeldingen')
            ->multiple()
            ->maxFiles(10)
            ->image()
            ->directory('galleries')
            ->preserveFilenames()
            ->reorderable(),

        ];
    }
}
