<?php

namespace App\Filament\Curator;

use App\Support\PhotoTrips;
use Awcodes\Curator\Actions\MultiUploadAction;
use Awcodes\Curator\Models\Media;
use Awcodes\Curator\Resources\Media\Pages\ListMedia as CuratorListMedia;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;

/**
 * Curator's media library, with a trip to choose when uploading a batch of photos: they are
 * linked to it straight away (the latest trip is suggested). Set in AppServiceProvider.
 */
class ListMedia extends CuratorListMedia
{
    public function getHeaderActions(): array
    {
        return array_map(
            fn (Action $action): Action => $action instanceof MultiUploadAction ? static::withTrip($action) : $action,
            parent::getHeaderActions(),
        );
    }

    protected static function withTrip(MultiUploadAction $action): MultiUploadAction
    {
        // Curator's own upload and saving, with the trip added to the form...
        $fields = (fn () => $this->schema)->call($action);
        $store = (fn () => $this->action)->call($action);

        return $action
            ->schema([
                static::tripField()
                    ->default(fn () => PhotoTrips::suggestion())
                    ->helperText('De foto\'s worden aan deze reis gekoppeld. Leeg: dan telt de reis van de blog waar ze later in komen.'),
                ...$fields,
            ])
            // ...and the new photos linked to it once they are stored
            ->action(function (array $data) use ($action, $store): void {
                $newerThan = Media::query()->max('id') ?? 0;

                $action->evaluate($store, ['data' => $data]);

                PhotoTrips::link(Media::query()->where('id', '>', $newerThan)->pluck('id'), $data['trip_id'] ?? null);
            });
    }

    public static function tripField(): Select
    {
        return Select::make('trip_id')
            ->label('Reis')
            ->placeholder('Geen reis')
            ->options(fn () => PhotoTrips::options())
            ->searchable();
    }
}
