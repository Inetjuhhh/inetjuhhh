<?php

namespace App\Filament\Blocks;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

class MapBlock extends BlogBlock
{
    public const ZOOM_LEVELS = [
        '5' => 'Heel land',
        '8' => 'Regio',
        '12' => 'Stad',
        '15' => 'Precieze plek',
    ];

    public static function getId(): string
    {
        return 'map';
    }

    public static function getLabel(): string
    {
        return 'Kaart';
    }

    public static function getIcon(): Heroicon
    {
        return Heroicon::OutlinedMapPin;
    }

    public static function getPreviewLabel(array $config): string
    {
        return 'Kaart · ' . ($config['place'] ?? '');
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalHeading('Kaart')
            ->modalDescription('Toont een Google Maps-kaart van een plek. Een plaatsnaam is genoeg.')
            ->schema([
                TextInput::make('place')
                    ->label('Plek of adres')
                    ->placeholder('Bijv. Ghorepani, Nepal')
                    ->required()
                    ->maxLength(255),
                Select::make('zoom')
                    ->label('Inzoomen op')
                    ->options(self::ZOOM_LEVELS)
                    ->default('8')
                    ->selectablePlaceholder(false),
                TextInput::make('caption')
                    ->label('Onderschrift (optioneel)')
                    ->maxLength(255),
            ]);
    }

    public static function toPreviewHtml(array $config): string
    {
        return view('filament.blocks.simple-preview', [
            'icon' => '📍',
            'title' => $config['place'] ?? 'Kaart',
            'text' => (self::ZOOM_LEVELS[$config['zoom'] ?? '8'] ?? '') . (filled($config['caption'] ?? null) ? ' · ' . $config['caption'] : ''),
        ])->render();
    }

    protected static function render(array $config): string
    {
        $place = trim($config['place'] ?? '');

        if ($place === '') {
            return '';
        }

        $zoom = array_key_exists($config['zoom'] ?? '', self::ZOOM_LEVELS) ? $config['zoom'] : '8';

        return view('blocks.map', [
            'place' => $place,
            'embed' => 'https://maps.google.com/maps?' . http_build_query(['q' => $place, 'z' => $zoom, 'output' => 'embed']),
            'link' => 'https://www.google.com/maps/search/?' . http_build_query(['api' => 1, 'query' => $place]),
            'caption' => $config['caption'] ?? null,
        ])->render();
    }
}
