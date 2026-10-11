<?php

namespace App\Filament\Resources\CountryResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\CountryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCountry extends EditRecord
{
    protected static string $resource = CountryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // Show the coordinates that were looked up while saving
        $this->refreshFormData(['latitude', 'longitude']);
    }
}
