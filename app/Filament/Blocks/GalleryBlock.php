<?php

namespace App\Filament\Blocks;

use App\Support\BlogImages;
use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Support\Icons\Heroicon;

class GalleryBlock extends BlogBlock
{
    public static function getId(): string
    {
        return 'gallery';
    }

    public static function getLabel(): string
    {
        return 'Fotogalerij';
    }

    public static function getIcon(): Heroicon
    {
        return Heroicon::OutlinedSquares2x2;
    }

    public static function getPreviewLabel(array $config): string
    {
        $count = count($config['media'] ?? []) + count($config['gallery_images'] ?? []);
        $layout = ($config['layout'] ?? 'grid') === 'slideshow' ? 'slideshow' : 'raster';

        return "Fotogalerij · {$count} foto's · {$layout}";
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalHeading('Fotogalerij')
            ->modalDescription('Kies foto\'s uit je mediabibliotheek of upload nieuwe. Je kunt de volgorde slepen.')
            ->modalWidth('3xl')
            ->schema([
                CuratorPicker::make('media')
                    ->label('Foto\'s')
                    ->buttonLabel('Foto\'s kiezen of uploaden')
                    ->multiple()
                    ->maxItems(40)
                    ->required(fn ($get) => blank($get('gallery_images'))),
                ToggleButtons::make('layout')
                    ->label('Weergave')
                    ->options([
                        'grid' => 'Raster',
                        'slideshow' => 'Slideshow',
                    ])
                    ->icons([
                        'grid' => Heroicon::OutlinedSquares2x2,
                        'slideshow' => Heroicon::OutlinedPlayCircle,
                    ])
                    ->default('grid')
                    ->inline(),
                TextInput::make('caption')
                    ->label('Onderschrift (optioneel)')
                    ->maxLength(255),
                // Photos added with the old editor, kept until they are replaced
                Hidden::make('gallery_images'),
            ]);
    }

    public static function toPreviewHtml(array $config): string
    {
        return view('filament.blocks.gallery-preview', [
            'images' => BlogImages::gallery($config)->take(8),
            'total' => BlogImages::gallery($config)->count(),
        ])->render();
    }

    protected static function render(array $config): string
    {
        $images = BlogImages::gallery($config);

        if ($images->isEmpty()) {
            return '';
        }

        return view('blocks.gallery', [
            'images' => $images,
            'layout' => $config['layout'] ?? (! empty($config['slideshow']) ? 'slideshow' : 'grid'),
            'caption' => $config['caption'] ?? null,
        ])->render();
    }
}
