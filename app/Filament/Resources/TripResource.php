<?php

namespace App\Filament\Resources;

use App\Filament\Forms\LocationPicker;
use App\Filament\Resources\TripResource\Pages\ManageTrips;
use App\Models\Trip;
use App\Support\BlogImages;
use App\Support\Geocoder;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                ->helperText('Voor een reis die nog komt: de plaatsen waar jullie naartoe gaan, in volgorde. Ze komen als stippellijn op de reiskaart. Schrijf plaats en land (bijv. "La Fortuna, Costa Rica") en druk op Enter: de pin komt op het kaartje ernaast. Verkeerde plek? Sleep de pin, of klik op het kaartje.')
                ->schema([
                    // Two columns: the place on the left, a small map with its pin on the right (stacked on a phone)
                    Grid::make(['default' => 1, 'md' => 2])
                        ->schema([
                            TextInput::make('name')
                                ->hiddenLabel()
                                ->placeholder('Plaats, land')
                                ->required()
                                ->maxLength(120)
                                // Looked up as soon as you leave the field (or press Enter), so the pin is on the map right away
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::findStop($get, $set))
                                // Enter looks the place up instead of saving the whole trip
                                ->extraInputAttributes(['x-on:keydown.enter.prevent.stop' => '$el.blur()'])
                                ->suffixAction(
                                    Action::make('findStop')
                                        ->icon(Heroicon::OutlinedMagnifyingGlass)
                                        ->tooltip('Zoek deze plek op de kaart')
                                        ->action(fn (Get $get, Set $set) => static::findStop($get, $set, again: true)),
                                ),
                            LocationPicker::make('pin')
                                ->hiddenLabel()
                                ->coordinates('lat', 'lng')
                                ->remembersPlaceIn('found_for', 'name')
                                // A stop that isn't found starts near the stop before it, not at the whole world
                                ->startsNearOtherItems()
                                ->compact()
                                // High enough, and zoomed out to the region, so you see where the place lies
                                ->mapHeight('h-56')
                                ->zoom(6)
                                ->emptyText('Nog geen pin: klik op het kaartje.'),
                        ]),
                    Hidden::make('lat'),
                    Hidden::make('lng'),
                    Hidden::make('found_for'),
                ])
                ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null)
                    ? $state['name'] . (filled($state['lat'] ?? null) ? ' · 📍 op de kaart' : ' · nog niet op de kaart')
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
     * Puts a stop of the planned route on the map by its name. A pin placed by hand stays, unless
     * the name changed (then it is about another place) or the search button is clicked.
     */
    public static function findStop(Get $get, Set $set, bool $again = false): void
    {
        $name = trim((string) $get('name'));

        if ($name === '' || (! $again && $get('found_for') === $name && filled($get('lat')))) {
            return;
        }

        if (! $found = Geocoder::find($name)) {
            Notification::make()
                ->title('Plek niet gevonden')
                ->body("\"{$name}\" staat niet op de kaart. Schrijf er het land bij, of klik zelf op de kaart om de stop te zetten.")
                ->warning()
                ->send();

            return;
        }

        $set('lat', round($found[0], 6));
        $set('lng', round($found[1], 6));
        $set('found_for', $name);
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
