<?php

namespace App\Filament\Resources\SubscriberResource\Pages;

use App\Filament\Resources\SubscriberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSubscribers extends ManageRecords
{
    protected static string $resource = SubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Added by hand: the owner already has their permission, so no confirmation mail
            CreateAction::make()
                ->label('Abonnee toevoegen')
                ->mutateDataUsing(fn (array $data) => $data + ['confirmed_at' => now()]),
        ];
    }
}
