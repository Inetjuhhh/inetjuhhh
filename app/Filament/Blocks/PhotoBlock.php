<?php

namespace App\Filament\Blocks;

use App\Support\BlogImages;
use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Support\Icons\Heroicon;

class PhotoBlock extends BlogBlock
{
    public static function getId(): string
    {
        return 'photo';
    }

    public static function getLabel(): string
    {
        return 'Foto met onderschrift';
    }

    public static function getIcon(): Heroicon
    {
        return Heroicon::OutlinedPhoto;
    }

    public static function getPreviewLabel(array $config): string
    {
        return filled($config['caption'] ?? null) ? 'Foto · ' . $config['caption'] : 'Foto';
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalHeading('Foto')
            ->modalWidth('2xl')
            ->schema([
                CuratorPicker::make('media')
                    ->label('Foto')
                    ->buttonLabel('Foto kiezen of uploaden')
                    ->required(),
                TextInput::make('caption')
                    ->label('Onderschrift (optioneel)')
                    ->placeholder('Bijv. Zonsopkomst boven de Annapurna')
                    ->maxLength(255),
                ToggleButtons::make('width')
                    ->label('Breedte')
                    ->options([
                        'normal' => 'Tekstbreedte',
                        'wide' => 'Extra breed',
                    ])
                    ->default('normal')
                    ->inline(),
            ]);
    }

    public static function toPreviewHtml(array $config): string
    {
        $media = BlogImages::media($config['media'] ?? null)->first();

        return view('filament.blocks.photo-preview', [
            'src' => $media ? BlogImages::url($media->path, BlogImages::WIDTH_THUMB * 2) : null,
            'caption' => $config['caption'] ?? null,
        ])->render();
    }

    protected static function render(array $config): string
    {
        $media = BlogImages::media($config['media'] ?? null)->first();

        if (! $media) {
            return '';
        }

        return view('blocks.photo', [
            'src' => BlogImages::url($media->path),
            'full' => BlogImages::url($media->path, 2400),
            'alt' => $media->alt ?: ($config['caption'] ?? ''),
            'caption' => $config['caption'] ?? null,
            'wide' => ($config['width'] ?? 'normal') === 'wide',
        ])->render();
    }
}
