<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriberResource\Pages\ManageSubscribers;
use App\Models\Subscriber;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Readers who get a mail for every new blog.
 */
class SubscriberResource extends Resource
{
    protected static ?string $model = Subscriber::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string | \UnitEnum | null $navigationGroup = 'Lezers';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'abonnee';

    protected static ?string $pluralModelLabel = 'abonnees';


    public static function getNavigationBadge(): ?string
    {
        return (string) Subscriber::confirmed()->count();
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Bevestigde abonnees';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Naam')
                    ->required()
                    ->maxLength(80),
                TextInput::make('email')
                    ->label('E-mailadres')
                    ->email()
                    ->required()
                    ->maxLength(190)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (string $state) => mb_strtolower(trim($state)))
                    ->helperText('Zet alleen iemand op de lijst die daar zelf om gevraagd heeft.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('E-mailadres')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('confirmed_at')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Subscriber $record) => $record->isConfirmed() ? 'Bevestigd' : 'Wacht op bevestiging')
                    ->color(fn (Subscriber $record) => $record->isConfirmed() ? 'success' : 'gray'),
                TextColumn::make('created_at')
                    ->label('Ingeschreven')
                    ->date('d-m-Y')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('confirmed_at')
                    ->label('Status')
                    ->nullable()
                    ->trueLabel('Bevestigd')
                    ->falseLabel('Wacht op bevestiging'),
            ])
            ->recordActions([
                DeleteAction::make()->label('Verwijderen'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSubscribers::route('/'),
        ];
    }
}
