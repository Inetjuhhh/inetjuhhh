<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TripResource\Pages\ManageTrips;
use App\Models\Trip;
use App\Support\BlogImages;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The trips, with the photo of their card on the Reizen page. Trips are also made from within a blog
 * (the "Reis" field); here they can be found together, and their card photo can be chosen.
 */
class TripResource extends Resource
{
    protected static ?string $model = Trip::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeEuropeAfrica;

    protected static string | \UnitEnum | null $navigationGroup = 'Schrijven';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'reis';

    protected static ?string $pluralModelLabel = 'reizen';


    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            ...BlogResource::tripSchema(),
            Select::make('cover_image_id')
                ->label('Foto voor de reiskaart')
                ->helperText('De foto op het kaartje van deze reis op de pagina Reizen. Kies uit de uitgelichte foto\'s van de verhalen; leeg laten = de foto van het middelste verhaal.')
                ->placeholder('Automatisch: de foto van het middelste verhaal')
                ->options(fn (?Trip $record): array => $record ? static::coverOptions($record) : [])
                ->allowHtml()
                ->native(false)
                ->searchable()
                ->visible(fn (?Trip $record): bool => $record && $record->coverChoices()->isNotEmpty())
                ->columnSpanFull(),
            Repeater::make('route')
                ->label('Geplande route')
                ->helperText('Voor een reis die nog komt: de plaatsen waar jullie naartoe gaan, in volgorde. Ze komen als stippellijn op de reiskaart. Schrijf plaats en land, bijv. "La Fortuna, Costa Rica".')
                ->schema([
                    TextInput::make('name')
                        ->hiddenLabel()
                        ->placeholder('Plaats, land')
                        ->required()
                        ->maxLength(120),
                    Hidden::make('lat'),
                    Hidden::make('lng'),
                    Hidden::make('found_for'),
                ])
                ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null)
                    ? $state['name'] . (filled($state['lat'] ?? null) && ($state['found_for'] ?? null) === $state['name'] ? ' · 📍 op de kaart' : ' · wordt opgezocht bij opslaan')
                    : null)
                ->addActionLabel('Stop toevoegen')
                ->reorderable()
                // A short list of names (with whether each was found); click a stop to change it
                ->collapsible()
                ->collapsed()
                ->defaultItems(0)
                ->columnSpanFull(),
        ]);
    }

    /**
     * The featured photos of the trip's stories, each shown with its story's title.
     *
     * @return array<int, string>
     */
    public static function coverOptions(Trip $trip): array
    {
        return $trip->parts()->loadMissing('featuredImage')->filter(fn ($blog) => $blog->featuredImage)
            ->unique('featured_image_id')
            ->mapWithKeys(fn ($blog) => [$blog->featured_image_id => '<span class="flex items-center gap-3">'
                . '<img src="' . e(BlogImages::mediaUrl($blog->featuredImage, 160)) . '" alt="" class="h-10 w-14 flex-none rounded object-cover">'
                . '<span>' . e($blog->title) . '</span></span>'])
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('coverImage')->withCount(['blogs' => fn ($query) => $query->published()]))
            ->columns([
                ImageColumn::make('cover')
                    ->label('')
                    ->state(fn (Trip $record) => ($photo = $record->coverMedia()) ? url(BlogImages::mediaUrl($photo, 160)) : null)
                    ->imageWidth(64)
                    ->imageHeight(44)
                    ->extraImgAttributes(['class' => 'rounded-md object-cover']),
                TextColumn::make('name')
                    ->label('Reis')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (Trip $record) => $record->period()),
                TextColumn::make('kind')
                    ->label('Soort')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Trip::KINDS[$state] ?? $state),
                // On a phone: photo, name and kind; the rest from tablet width
                TextColumn::make('blogs_count')
                    ->label('Verhalen')
                    ->visibleFrom('md')
                    ->numeric(),
                TextColumn::make('cover_choice')
                    ->label('Foto')
                    ->visibleFrom('md')
                    ->state(fn (Trip $record) => $record->cover_image_id ? 'Zelf gekozen' : 'Automatisch')
                    ->color(fn (Trip $record) => $record->cover_image_id ? 'primary' : 'gray'),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('2xl'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTrips::route('/'),
        ];
    }
}
