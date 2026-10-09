<?php

namespace App\Filament\Blocks;

use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

class PracticalInfoBlock extends BlogBlock
{
    public static function getId(): string
    {
        return 'practical-info';
    }

    public static function getLabel(): string
    {
        return 'Praktische info';
    }

    public static function getIcon(): Heroicon
    {
        return Heroicon::OutlinedClipboardDocumentList;
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalHeading('Praktische info')
            ->modalDescription('Een overzichtje met de belangrijkste info. Lege regels worden niet getoond.')
            ->modalWidth('2xl')
            ->schema([
                TextInput::make('title')
                    ->label('Titel')
                    ->default('Praktische info')
                    ->required()
                    ->maxLength(120),
                Repeater::make('items')
                    ->label('Regels')
                    ->schema([
                        TextInput::make('label')
                            ->label('Onderwerp')
                            ->required(),
                        TextInput::make('value')
                            ->label('Info'),
                    ])
                    ->columns(2)
                    ->default([
                        ['label' => 'Beste reistijd', 'value' => null],
                        ['label' => 'Hoe lang', 'value' => null],
                        ['label' => 'Budget', 'value' => null],
                        ['label' => 'Vervoer', 'value' => null],
                        ['label' => 'Overnachten', 'value' => null],
                    ])
                    ->reorderable()
                    ->addActionLabel('Regel toevoegen'),
            ]);
    }

    public static function toPreviewHtml(array $config): string
    {
        $items = static::filledItems($config);

        return view('filament.blocks.simple-preview', [
            'icon' => '📋',
            'title' => $config['title'] ?? 'Praktische info',
            'text' => collect($items)->map(fn ($item) => "{$item['label']}: {$item['value']}")->join(' · '),
        ])->render();
    }

    protected static function render(array $config): string
    {
        $items = static::filledItems($config);

        if ($items === []) {
            return '';
        }

        return view('blocks.practical-info', [
            'title' => $config['title'] ?? 'Praktische info',
            'items' => $items,
        ])->render();
    }

    private static function filledItems(array $config): array
    {
        return collect($config['items'] ?? [])
            ->filter(fn ($item) => filled($item['label'] ?? null) && filled($item['value'] ?? null))
            ->values()
            ->all();
    }
}
