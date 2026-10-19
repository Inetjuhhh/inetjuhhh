<?php

namespace App\Filament\Curator;

use App\Support\AltTextWriter;
use Awcodes\Curator\Resources\Media\Pages\EditMedia as CuratorEditMedia;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * Curator's page for one photo, with a button that lets Claude write the description (alt text).
 * The suggestion goes into the field; nothing is saved until Ine clicks Save. Set in AppServiceProvider.
 */
class EditMedia extends CuratorEditMedia
{
    public function getHeaderActions(): array
    {
        return [
            Action::make('describe')
                ->label('Beschrijf met AI')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->tooltip('Claude kijkt naar de foto en stelt een beschrijving voor')
                ->visible(fn () => str_starts_with((string) $this->record->type, 'image/'))
                ->action(function () {
                    try {
                        $alt = app(AltTextWriter::class)->describe($this->record);
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()->danger()->title('Beschrijven lukte niet')->body($e->getMessage())->send();

                        return;
                    }

                    $this->data['alt'] = $alt;

                    Notification::make()->success()->title('Beschrijving voorgesteld')
                        ->body('Pas hem gerust aan, en klik daarna op Opslaan.')->send();
                }),
            ...parent::getHeaderActions(),
        ];
    }
}
