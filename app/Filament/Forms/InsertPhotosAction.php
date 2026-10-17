<?php

namespace App\Filament\Forms;

use App\Filament\Blocks\BlogBlock;
use App\Filament\Blocks\GalleryBlock;
use App\Filament\Blocks\PhotoBlock;
use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * The photo button in the story editor: choose or upload one or more photos, and with more than one
 * choose whether they become a photo gallery or photos one below the other. Each becomes a photo
 * block (with a caption) or one gallery block, the same blocks as under "Blokken".
 */
class InsertPhotosAction
{
    public const NAME = 'insertPhotos';

    public static function tool(): RichEditorTool
    {
        return RichEditorTool::make(self::NAME)
            ->label('Foto\'s')
            ->icon(Heroicon::OutlinedPhoto)
            ->action();
    }

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->modalHeading('Foto\'s toevoegen')
            ->modalDescription('Kies een of meer foto\'s uit je mediabibliotheek of upload nieuwe.')
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Invoegen')
            ->schema([
                CuratorPicker::make('media')
                    ->label('Foto\'s')
                    ->buttonLabel('Foto\'s kiezen of uploaden')
                    ->multiple()
                    ->maxItems(40)
                    ->live()
                    ->required(),
                Toggle::make('as_gallery')
                    ->label('Als fotogalerij')
                    ->helperText(fn (Get $get): string => $get('as_gallery')
                        ? 'Alle foto\'s samen in één galerij: liggende en staande gemengd, niets wordt afgesneden.'
                        : 'Elke foto apart, onder elkaar over de breedte van je tekst.')
                    ->default(true)
                    ->live()
                    ->visible(fn (Get $get): bool => count($get('media') ?? []) > 1),
                TextInput::make('caption')
                    ->label('Onderschrift (optioneel)')
                    ->helperText(fn (Get $get): ?string => count($get('media') ?? []) > 1 && ! $get('as_gallery')
                        ? 'Komt onder de laatste foto.'
                        : null)
                    ->maxLength(255),
            ])
            ->action(function (array $arguments, array $data, RichEditor $component): void {
                $nodes = static::blocks(
                    array_values($data['media'] ?? []),
                    (bool) ($data['as_gallery'] ?? true),
                    filled($data['caption'] ?? null) ? $data['caption'] : null,
                );

                if ($nodes === []) {
                    return;
                }

                $component->runCommands(
                    [EditorCommand::make('insertContent', arguments: [$nodes])],
                    editorSelection: $arguments['editorSelection'] ?? null,
                );
            });
    }

    /**
     * Several photos as one gallery, or each as a photo (the caption under the last one).
     *
     * @param  list<mixed>  $media  as the media picker gives them
     * @return list<array<string, mixed>>
     */
    public static function blocks(array $media, bool $asGallery, ?string $caption): array
    {
        if (count($media) > 1 && $asGallery) {
            return [static::node(GalleryBlock::class, array_filter(['media' => $media, 'layout' => 'grid', 'caption' => $caption]))];
        }

        return array_map(fn ($item, int $i) => static::node(PhotoBlock::class, array_filter([
            'media' => $item,
            'caption' => $i === count($media) - 1 ? $caption : null,
            'width' => 'normal',
        ])), $media, array_keys($media));
    }

    /**
     * A block as the editor stores it (the same shape Filament's own block button makes).
     *
     * @param  class-string<BlogBlock>  $block
     */
    private static function node(string $block, array $config): array
    {
        return [
            'type' => 'customBlock',
            'attrs' => [
                'config' => $config,
                'id' => $block::getId(),
                'label' => $block::getPreviewLabel($config),
                'preview' => base64_encode($block::toPreviewHtml($config)),
                'shouldApplyProseStylingToPreview' => $block::shouldApplyProseStylingToPreview($config),
            ],
        ];
    }
}
