<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ResponseResource;
use App\Models\Response;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Comments waiting for approval, to approve straight from the dashboard.
 */
class WaitingComments extends TableWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = 'Wacht op goedkeuring';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Response::pending()->where('is_author', false)->with('blog')->latest()->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('response')
                    ->label('Reactie')
                    ->limit(110)
                    ->wrap()
                    ->description(fn (Response $record) => "{$record->name} · " . $record->created_at->diffForHumans() . ' · bij ' . str($record->blog?->title)->limit(40), position: 'above'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Goedkeuren')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Goedkeuren: de reactie komt online')
                    ->action(fn (Response $record) => $record->approve()),
            ])
            ->headerActions([
                Action::make('all')
                    ->label('Alle reacties')
                    ->link()
                    ->url(ResponseResource::getUrl('index')),
            ])
            ->emptyStateHeading('Niets te beoordelen')
            ->emptyStateDescription('Nieuwe reacties van onbekende lezers verschijnen hier eerst.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
