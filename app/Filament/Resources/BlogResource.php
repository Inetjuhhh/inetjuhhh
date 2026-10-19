<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlogResource\Pages\CreateBlog;
use App\Filament\Resources\BlogResource\Pages\EditBlog;
use App\Filament\Resources\BlogResource\Pages\ListBlogs;
use App\Filament\Forms\LocationPicker;
use App\Filament\Forms\StoryEditor;
use App\Models\Blog;
use App\Models\Subscriber;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Trip;
use App\Support\BlogSearch;
use App\Support\Geocoder;
use App\Support\ImageOptimizer;
use App\Support\NewBlogNotifier;
use App\Support\TagSuggester;
use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BlogResource extends Resource
{
    protected static ?string $model = Blog::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string | \UnitEnum | null $navigationGroup = 'Schrijven';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'blog';

    protected static ?string $pluralModelLabel = 'blogs';


    // Admin URLs use the id: the slug can change while editing
    protected static ?string $recordRouteKeyName = 'id';

    public const STATUSES = [
        'draft' => 'Concept',
        'published' => 'Gepubliceerd',
        'archived' => 'Gearchiveerd',
    ];

    public static function form(Schema $schema): Schema
    {
        // Drafts may be incomplete; only a title is needed to save (and autosave) one
        $requiredWhenPublished = fn (Get $get): bool => $get('status') === 'published';

        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->columnSpanFull()
                    // On a phone: tabs "Verhaal" and "Details" (pane-switch.blade.php, theme.css). A field with an
                    // error opens its own tab, so a missing country or category is never out of sight.
                    ->extraAttributes([
                        'class' => 'blog-panes',
                        'x-data' => "{ pane: 'story' }",
                        'x-bind:data-pane' => 'pane',
                        'x-on:form-validation-error.window' => <<<'JS'
                            $nextTick(() => {
                                const error = $el.querySelector('[data-validation-error]');
                                const column = error?.closest('[data-pane-col]');
                                if (column) { pane = column.dataset.paneCol; $nextTick(() => error.scrollIntoView({ block: 'center' })) }
                            })
                            JS,
                    ])
                    ->schema([
                        View::make('filament.blogs.pane-switch')->columnSpanFull(),

                        Group::make([
                            Section::make()
                                ->schema([
                                    TextInput::make('title')
                                        ->label('Titel')
                                        ->placeholder('Waar gaat je verhaal over?')
                                        ->required()
                                        ->maxLength(255)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation) {
                                            // Keep the link stable once a blog has been published
                                            if ($operation === 'create' || $get('status') !== 'published') {
                                                $set('slug', Str::slug($state));
                                            }
                                        }),
                                    TextInput::make('slug')
                                        ->label('Link')
                                        ->prefix('/blogs/')
                                        ->required()
                                        // While creating, the draft made by autosave owns this link already
                                        ->unique(ignorable: fn ($livewire, ?Blog $record) => $record
                                            ?? ($livewire instanceof CreateBlog ? $livewire->autosavedRecord() : null))
                                        ->maxLength(255),
                                    Textarea::make('excerpt')
                                        ->label('Intro')
                                        ->helperText('Een of twee zinnen. Staat op de overzichtspagina en bovenaan je verhaal.')
                                        ->rows(2)
                                        ->dehydrateStateUsing(fn (?string $state) => $state ?? '')
                                        ->required($requiredWhenPublished)
                                        ->maxLength(500),
                                ]),
                            StoryEditor::make('content')
                                ->label('Verhaal')
                                ->required($requiredWhenPublished),
                        ])->columnSpan(['lg' => 2])->extraAttributes(['data-pane-col' => 'story']),

                        Group::make([
                            Section::make('Publiceren')
                                ->icon(Heroicon::OutlinedPaperAirplane)
                                ->schema([
                                    ToggleButtons::make('status')
                                        ->label('Status')
                                        ->options(self::STATUSES)
                                        ->colors(['draft' => 'gray', 'published' => 'success', 'archived' => 'warning'])
                                        ->icons([
                                            'draft' => Heroicon::OutlinedPencil,
                                            'published' => Heroicon::OutlinedGlobeAlt,
                                            'archived' => Heroicon::OutlinedArchiveBox,
                                        ])
                                        ->default('draft')
                                        ->live()
                                        ->required(),
                                    DateTimePicker::make('published_at')
                                        ->label('Publicatiedatum')
                                        ->helperText('Leeg laten = meteen. Kies een datum in de toekomst om je blog in te plannen.')
                                        ->seconds(false)
                                        ->native(false)
                                        ->displayFormat('d-m-Y H:i'),
                                ]),
                            Section::make('Uitgelichte foto')
                                ->icon(Heroicon::OutlinedPhoto)
                                ->schema([
                                    CuratorPicker::make('featured_image_id')
                                        ->hiddenLabel()
                                        ->buttonLabel('Foto kiezen of uploaden')
                                        // Its own folder, so uploads here may be bigger than photos in the story
                                        ->directory(ImageOptimizer::FEATURED_DIRECTORY)
                                        ->relationship('featuredImage', 'id')
                                ]),
                            Section::make('Waar gaat het over?')
                                ->icon(Heroicon::OutlinedMapPin)
                                ->schema([
                                    Select::make('trip_id')
                                        ->label('Reis')
                                        ->helperText('Hoort deze blog bij een reis? Lezers kunnen dan alle delen achter elkaar lezen.')
                                        ->relationship('trip', 'name', fn ($query) => $query->latest('starts_on'))
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Geen reis')
                                        ->createOptionForm(self::tripSchema())
                                        ->editOptionForm(self::tripSchema())
                                        ->createOptionModalHeading('Nieuwe reis')
                                        ->editOptionModalHeading('Reis bewerken'),
                                    Select::make('countries')
                                        ->label('Land(en)')
                                        ->relationship('countries', 'name')
                                        ->multiple()
                                        ->preload()
                                        ->searchable()
                                        ->createOptionForm([
                                            TextInput::make('name')->label('Land')->required(),
                                        ])
                                        ->helperText('Alleen nodig bij een reisverhaal: dan staat het land op de foto en op de kaart.'),
                                    Select::make('categories')
                                        ->label('Categorie')
                                        ->relationship('categories', 'name')
                                        ->multiple()
                                        ->preload()
                                        ->createOptionForm([
                                            TextInput::make('name')->label('Categorie')->required(),
                                        ])
                                        ->required($requiredWhenPublished),
                                    Select::make('tags')
                                        ->label('Tags')
                                        ->relationship('tags', 'name')
                                        ->multiple()
                                        ->preload()
                                        ->searchable()
                                        ->createOptionForm([
                                            TextInput::make('name')->label('Tag')->required(),
                                        ])
                                        ->hintAction(
                                            Action::make('suggestTags')
                                                ->label('Stel voor')
                                                ->icon(Heroicon::OutlinedSparkles)
                                                ->tooltip('Laat Claude passende onderwerpen kiezen uit je bestaande tags')
                                                ->action(fn (Get $get, Set $set) => self::suggestTags($get, $set))
                                        ),
                                ]),
                            Section::make('Op de reiskaart')
                                ->icon(Heroicon::OutlinedMap)
                                ->schema([
                                    TextInput::make('location_name')
                                        ->label('Plek')
                                        ->placeholder('Bijv. Ghorepani, Nepal')
                                        ->helperText('Na het typen zet de app een pin op die plek, als er nog geen pin staat.')
                                        ->maxLength(255)
                                        ->live(onBlur: true)
                                        // A suggestion only: a pin that is already there (maybe placed by hand) stays put
                                        ->afterStateUpdated(fn (Get $get, Set $set) => blank($get('latitude'))
                                            ? self::suggestPin($get, $set)
                                            : null)
                                        ->suffixAction(
                                            Action::make('suggestPin')
                                                ->icon(Heroicon::OutlinedMagnifyingGlass)
                                                ->tooltip('Zet de pin op deze plek')
                                                ->action(fn (Get $get, Set $set) => self::suggestPin($get, $set)),
                                        ),
                                    LocationPicker::make('pin')
                                        ->label('Pin op de kaart'),
                                    Grid::make(2)
                                        ->schema([
                                            TextInput::make('latitude')
                                                ->label('Breedtegraad')
                                                ->numeric()
                                                ->minValue(-90)
                                                ->maxValue(90),
                                            TextInput::make('longitude')
                                                ->label('Lengtegraad')
                                                ->numeric()
                                                ->minValue(-180)
                                                ->maxValue(180),
                                        ]),
                                ])
                                ->description('De app doet een voorstel op basis van de plek; je kunt de pin altijd zelf verzetten.')
                                ->collapsible(),
                        ])->columnSpan(['lg' => 1])->extraAttributes(['data-pane-col' => 'details']),
                    ]),
            ]);
    }

    /**
     * Fields for creating or editing a trip from within the blog form.
     */
    public static function tripSchema(): array
    {
        return [
            TextInput::make('name')
                ->label('Naam van de reis')
                ->placeholder('Bijv. Vietnam & Cambodja 2025')
                ->required()
                ->maxLength(255),
            ToggleButtons::make('kind')
                ->label('Soort')
                ->helperText('Staat als label op het kaartje van de reis op de pagina Reizen.')
                ->options(Trip::KINDS)
                ->icons([
                    Trip::KIND_TRIP => Heroicon::OutlinedGlobeEuropeAfrica,
                    Trip::KIND_CITY => Heroicon::OutlinedBuildingOffice2,
                ])
                ->default(Trip::KIND_TRIP)
                ->inline()
                ->required(),
            Grid::make(2)->schema([
                DatePicker::make('starts_on')
                    ->label('Van')
                    ->native(false)
                    ->displayFormat('d-m-Y')
                    ->live()
                    // Moved past the end date: the end date moves along, so it can never be before the start
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                        if ($state && filled($get('ends_on')) && $get('ends_on') < $state) {
                            $set('ends_on', $state);
                        }
                    }),
                DatePicker::make('ends_on')
                    ->label('Tot')
                    ->native(false)
                    ->displayFormat('d-m-Y')
                    ->minDate(fn (Get $get) => $get('starts_on') ?: null)
                    ->afterOrEqual('starts_on'),
            ]),
            Textarea::make('description')
                ->label('Korte beschrijving')
                ->helperText('Staat bovenaan als lezers alle verhalen van deze reis bekijken.')
                ->rows(3)
                ->maxLength(500),
        ];
    }

    /**
     * Places the pin on the place typed in the form, as a suggestion the writer can still move.
     */
    public static function suggestPin(Get $get, Set $set): void
    {
        $place = trim((string) $get('location_name'));

        if ($place === '') {
            return;
        }

        if (! $found = Geocoder::find($place)) {
            Notification::make()
                ->title('Plek niet gevonden')
                ->body("\"{$place}\" staat niet op de kaart. Klik zelf op de kaart om de pin te zetten.")
                ->warning()
                ->send();

            return;
        }

        $set('latitude', round($found[0], 6));
        $set('longitude', round($found[1], 6));
    }

    // Adds Claude's picks to the tags already chosen; it only picks from the existing tags
    public static function suggestTags(Get $get, Set $set): void
    {
        $suggester = app(TagSuggester::class);

        if (! $suggester->isConfigured()) {
            Notification::make()
                ->danger()
                ->title('Geen AI-sleutel ingesteld')
                ->body('Zet ANTHROPIC_API_KEY in het .env-bestand om onderwerpen te laten voorstellen.')
                ->send();

            return;
        }

        $title = (string) $get('title');
        $text = BlogSearch::plainText($get('content'));

        if (blank($title) && blank($text)) {
            Notification::make()->warning()->title('Schrijf eerst een titel of een stukje verhaal')->send();

            return;
        }

        try {
            $names = $suggester->suggest(
                title: $title,
                intro: (string) $get('excerpt'),
                // The start of a long story says enough, and keeps the request small
                text: Str::limit($text, 12000),
                categories: Category::whereKey($get('categories') ?? [])->pluck('name')->all(),
            );
        } catch (\Throwable $e) {
            report($e);
            Notification::make()
                ->danger()
                ->title('Onderwerpen voorstellen lukte niet')
                ->body($e instanceof \RuntimeException ? $e->getMessage() : 'Probeer het zo nog eens.')
                ->send();

            return;
        }

        if ($names === []) {
            Notification::make()->warning()->title('Geen passend onderwerp gevonden')->send();

            return;
        }

        $chosen = array_map('strval', $get('tags') ?? []);
        $suggested = Tag::whereIn('name', $names)->pluck('id')->map(fn ($id) => (string) $id)->all();
        $set('tags', array_values(array_unique([...$chosen, ...$suggested])));

        Notification::make()
            ->success()
            ->title('Voorgesteld: ' . implode(', ', $names))
            ->body('Klopt er een niet? Haal hem gewoon weg.')
            ->send();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover')
                    ->label('')
                    // A full URL: Filament reads a relative one as a file on the disk
                    ->state(fn (Blog $record) => url($record->coverUrl(160, 120)))
                    ->imageWidth(64)
                    ->imageHeight(48)
                    ->visibleFrom('sm'),
                TextColumn::make('title_on_phone')
                    ->label('Blog')
                    ->state(fn (Blog $record) => $record->title)
                    ->wrap()
                    ->weight('medium')
                    ->description(fn (Blog $record) => collect([
                        self::statusLabel($record),
                        $record->countries->pluck('name')->join(', '),
                        $record->trip ? '🧭 ' . $record->trip->name : null,
                    ])->filter()->join(' · '))
                    ->hiddenFrom('sm'),
                TextColumn::make('title')
                    ->label('Titel')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('sm')
                    ->wrap()
                    ->description(fn (Blog $record) => collect([
                        $record->countries->pluck('name')->join(', '),
                        $record->trip ? '🧭 ' . $record->trip->name : null,
                    ])->filter()->join(' · ')),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (Blog $record) => self::statusLabel($record))
                    ->color(fn (Blog $record, string $state) => match (true) {
                        $state === 'published' && ! $record->isPublished() => 'info',
                        $state === 'published' => 'success',
                        $state === 'archived' => 'warning',
                        default => 'gray',
                    })
                    ->sortable()
                    ->visibleFrom('sm'),
                TextColumn::make('published_at')
                    ->label('Publicatiedatum')
                    ->dateTime('d-m-Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->visibleFrom('md'),
                IconColumn::make('subscribers_notified_at')
                    ->label('Mail')
                    ->state(fn (Blog $record) => match (true) {
                        $record->subscribers_notified_at !== null => 'sent',
                        $record->isPublished() => 'ready',
                        default => null,
                    })
                    ->icon(fn (?string $state) => $state === 'sent' ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedEnvelope)
                    ->color(fn (?string $state) => $state === 'sent' ? 'success' : 'gray')
                    ->tooltip(fn (Blog $record) => $record->subscribers_notified_at
                        ? 'Gemaild op ' . $record->subscribers_notified_at->translatedFormat('d F Y H:i')
                        : ($record->isPublished() ? 'Nog niet gemaild' : null))
                    ->alignCenter()
                    ->visibleFrom('md'),
                TextColumn::make('updated_at')
                    ->label('Laatst bewerkt')
                    ->since()
                    ->sortable()
                    ->visibleFrom('xl'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['countries', 'trip', 'featuredImage']))
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(self::STATUSES)
                    ->default('published'),
                SelectFilter::make('countries')
                    ->label('Land')
                    ->relationship('countries', 'name')
                    ->preload(),
                SelectFilter::make('trip')
                    ->label('Reis')
                    ->relationship('trip', 'name')
                    ->preload(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Bekijk')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (Blog $record) => $record->isPublished()
                        ? route('blogs.show', $record)
                        : $record->previewUrl())
                    ->openUrlInNewTab()
                    ->iconButton()
                    ->tooltip('Bekijk op de site'),
                self::mailSubscribersAction()->iconButton(),
                EditAction::make()->iconButton()->tooltip('Bewerken'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    // "Ingepland" for a published blog whose date is still to come
    /**
     * "Mail abonnees": the mail about a new blog to everyone on the list. Only for a blog that is online
     * and hasn't been sent yet; asks first, because it can only be sent once.
     */
    public static function mailSubscribersAction(): Action
    {
        return Action::make('mailSubscribers')
            ->label('Mail abonnees')
            ->tooltip('Mail abonnees over deze blog')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->visible(fn (Blog $record) => NewBlogNotifier::canSend($record))
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedEnvelope)
            ->modalHeading('Mail naar je abonnees sturen?')
            ->modalDescription(function (Blog $record) {
                $count = Subscriber::confirmed()->count();

                return $count === 0
                    ? 'Er staan nog geen bevestigde abonnees op de lijst. Er gaat dus niets weg; de knop blijft staan voor later.'
                    : "\"{$record->title}\" gaat naar {$count} " . ($count === 1 ? 'abonnee' : 'abonnees') . '. Dat kan maar één keer: daarna verdwijnt deze knop.';
            })
            ->modalSubmitActionLabel('Ja, verstuur')
            ->action(function (Blog $record) {
                $count = NewBlogNotifier::send($record);

                $count > 0
                    ? Notification::make()->success()->title("Verstuurd naar {$count} " . ($count === 1 ? 'abonnee' : 'abonnees'))
                        ->body('De mails gaan de komende minuten de deur uit.')->send()
                    : Notification::make()->warning()->title('Er is niets verstuurd')
                        ->body('Er zijn nog geen bevestigde abonnees, of deze blog was al verstuurd.')->send();
            });
    }

    /**
     * "Testmail naar mij": the same mail to yourself first, to see how it looks. Marks nothing as sent.
     */
    public static function testMailAction(): Action
    {
        return Action::make('testMail')
            ->label('Testmail naar mij')
            ->tooltip('Stuurt de mail voor abonnees eerst naar je eigen adres')
            ->icon(Heroicon::OutlinedEnvelopeOpen)
            ->color('gray')
            ->visible(fn (Blog $record) => $record->subscribers_notified_at === null)
            ->action(function (Blog $record) {
                $user = auth()->user();

                try {
                    NewBlogNotifier::sendTest($record, $user->email, $user->name);
                } catch (\Throwable $e) {
                    report($e);
                    Notification::make()->danger()->title('De testmail kon niet worden verstuurd')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Testmail verstuurd naar ' . $user->email)
                    ->body($record->isPublished() ? null : 'Let op: de blog staat nog niet online, dus de knop in de mail opent nog niets.')
                    ->send();
            });
    }

    private static function statusLabel(Blog $record): string
    {
        return $record->status === 'published' && ! $record->isPublished()
            ? 'Ingepland'
            : (self::STATUSES[$record->status] ?? $record->status);
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogs::route('/'),
            'create' => CreateBlog::route('/create'),
            // ":id" makes the links use the id too (the Blog model's own route key is the slug)
            'edit' => EditBlog::route('/{record:id}/edit'),
        ];
    }
}
