<?php

namespace App\Filament\Blocks;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Support\Icons\Heroicon;

class TipBlock extends BlogBlock
{
    public const TYPES = [
        'tip' => ['label' => 'Tip', 'emoji' => '💡'],
        'warning' => ['label' => 'Let op', 'emoji' => '⚠️'],
        'info' => ['label' => 'Goed om te weten', 'emoji' => 'ℹ️'],
    ];

    public static function getId(): string
    {
        return 'tip';
    }

    public static function getLabel(): string
    {
        return 'Tip of waarschuwing';
    }

    public static function getIcon(): Heroicon
    {
        return Heroicon::OutlinedLightBulb;
    }

    public static function getPreviewLabel(array $config): string
    {
        return self::TYPES[$config['type'] ?? 'tip']['label'] ?? 'Tip';
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalHeading('Tip of waarschuwing')
            ->schema([
                ToggleButtons::make('type')
                    ->label('Soort')
                    ->options(collect(self::TYPES)->map(fn ($type) => $type['label'])->all())
                    ->icons([
                        'tip' => Heroicon::OutlinedLightBulb,
                        'warning' => Heroicon::OutlinedExclamationTriangle,
                        'info' => Heroicon::OutlinedInformationCircle,
                    ])
                    ->colors([
                        'tip' => 'success',
                        'warning' => 'warning',
                        'info' => 'info',
                    ])
                    ->default('tip')
                    ->inline(),
                TextInput::make('title')
                    ->label('Titel (optioneel)')
                    ->placeholder('Bijv. Neem contant geld mee')
                    ->maxLength(120),
                Textarea::make('text')
                    ->label('Tekst')
                    ->rows(4)
                    ->required(),
            ]);
    }

    public static function toPreviewHtml(array $config): string
    {
        $type = self::TYPES[$config['type'] ?? 'tip'] ?? self::TYPES['tip'];

        return view('filament.blocks.simple-preview', [
            'icon' => $type['emoji'],
            'title' => $config['title'] ?? $type['label'],
            'text' => $config['text'] ?? '',
        ])->render();
    }

    protected static function render(array $config): string
    {
        $key = array_key_exists($config['type'] ?? '', self::TYPES) ? $config['type'] : 'tip';

        return view('blocks.tip', [
            'type' => $key,
            'emoji' => self::TYPES[$key]['emoji'],
            'title' => filled($config['title'] ?? null) ? $config['title'] : self::TYPES[$key]['label'],
            'text' => $config['text'] ?? '',
        ])->render();
    }
}
