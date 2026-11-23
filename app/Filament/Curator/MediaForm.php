<?php

namespace App\Filament\Curator;

use App\Support\PhotoTrips;
use Awcodes\Curator\Models\Media;
use Awcodes\Curator\Resources\Media\Schemas\MediaForm as CuratorMediaForm;

/**
 * Curator's form of one photo, with the trip it belongs to. Set in AppServiceProvider.
 */
class MediaForm extends CuratorMediaForm
{
    public static function getAdditionalInformationFormSchema(): array
    {
        return [
            ...parent::getAdditionalInformationFormSchema(),
            ListMedia::tripField()
                ->hiddenOn('create')
                ->placeholder(fn (?Media $record) => ($trip = $record?->blogs->first()?->trip)
                    ? "Via de blog: {$trip->name}"
                    : 'Geen reis')
                ->helperText('Leeg: dan telt de reis van de blog waar de foto in staat.'),
        ];
    }
}
