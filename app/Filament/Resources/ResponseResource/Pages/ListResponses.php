<?php

namespace App\Filament\Resources\ResponseResource\Pages;

use App\Filament\Resources\ResponseResource;
use App\Models\Response;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListResponses extends ListRecords
{
    protected static string $resource = ResponseResource::class;

    // Short tab names, so all four fit on a phone
    private const TAB_LABELS = [Response::PENDING => 'Wacht', Response::APPROVED => 'Online', Response::SPAM => 'Spam'];

    // Waiting comments first: that's usually why you open this page
    public function getTabs(): array
    {
        $tab = fn (string $status) => Tab::make(self::TAB_LABELS[$status])
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status))
            ->badge(Response::where('status', $status)->where('is_author', false)->count());

        return [
            Response::PENDING => $tab(Response::PENDING)->badgeColor('warning'),
            Response::APPROVED => $tab(Response::APPROVED),
            Response::SPAM => $tab(Response::SPAM)->badgeColor('danger'),
            'all' => Tab::make('Alles'),
        ];
    }
}
