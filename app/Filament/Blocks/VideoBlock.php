<?php

namespace App\Filament\Blocks;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

class VideoBlock extends BlogBlock
{
    public static function getId(): string
    {
        return 'video';
    }

    public static function getLabel(): string
    {
        return 'Video (YouTube of Vimeo)';
    }

    public static function getIcon(): Heroicon
    {
        return Heroicon::OutlinedVideoCamera;
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalHeading('Video')
            ->schema([
                TextInput::make('url')
                    ->label('Link naar de video')
                    ->placeholder('https://www.youtube.com/watch?v=...')
                    ->url()
                    ->required()
                    ->rule(fn (): Closure => function (string $attribute, $value, Closure $fail) {
                        if (! static::embedUrl($value)) {
                            $fail('Gebruik een link van YouTube of Vimeo.');
                        }
                    }),
                TextInput::make('caption')
                    ->label('Onderschrift (optioneel)')
                    ->maxLength(255),
            ]);
    }

    public static function toPreviewHtml(array $config): string
    {
        return view('filament.blocks.simple-preview', [
            'icon' => '▶',
            'title' => 'Video',
            'text' => $config['caption'] ?? ($config['url'] ?? ''),
        ])->render();
    }

    protected static function render(array $config): string
    {
        $embed = static::embedUrl($config['url'] ?? '');

        if (! $embed) {
            return '';
        }

        return view('blocks.video', [
            'embed' => $embed,
            'caption' => $config['caption'] ?? null,
        ])->render();
    }

    public static function embedUrl(?string $url): ?string
    {
        $url = (string) $url;

        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return "https://www.youtube-nocookie.com/embed/{$m[1]}";
        }

        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return "https://player.vimeo.com/video/{$m[1]}";
        }

        return null;
    }
}
