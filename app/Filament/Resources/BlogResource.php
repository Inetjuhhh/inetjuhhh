<?php

namespace App\Filament\Resources;

use App\Filament\Blocks\BlogBlock;
use App\Filament\Resources\BlogResource\Pages\CreateBlog;
use App\Filament\Resources\BlogResource\Pages\EditBlog;
use App\Filament\Resources\BlogResource\Pages\ListBlogs;
use App\Filament\Forms\LocationPicker;
use App\Models\Blog;
use App\Support\Geocoder;
use App\Support\ImageOptimizer;
use Awcodes\Curator\Components\Forms\CuratorPicker;
use Awcodes\Curator\Components\Forms\RichEditor\AttachCuratorMediaPlugin;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BlogResource extends Resource
{
    protected static ?string $model = Blog::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $modelLabel = 'blog';

    protected static ?string $pluralModelLabel = 'blogs';

    protected static ?int $navigationSort = -1;

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
                    ->schema([
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
                            RichEditor::make('content')
                                ->label('Verhaal')
                                ->json()
                                ->toolbarButtons([
                                    ['bold', 'italic', 'link'],
                                    ['h2', 'h3'],
                                    ['blockquote', 'bulletList', 'orderedList', 'horizontalRule'],
                                    ['attachCuratorMedia', 'customBlocks', 'grid', 'table'],
                                    ['undo', 'redo'],
                                ])
                                ->floatingToolbars([
                                    'paragraph' => ['bold', 'italic', 'link', 'h2', 'h3'],
                                    'heading' => ['h2', 'h3'],
                                    'grid' => ['gridAddColumnBefore', 'gridAddColumnAfter', 'gridDeleteColumn', 'gridDelete'],
                                    'table' => ['tableAddColumnAfter', 'tableDeleteColumn', 'tableAddRowAfter', 'tableDeleteRow', 'tableDelete'],
                                ])
                                ->plugins([AttachCuratorMediaPlugin::make()])
                                ->customBlocks(BlogBlock::all())
                                ->searchableCustomBlocks(false)
                                ->stickyToolbar()
                                ->resizableImages()
                                ->extraInputAttributes(['style' => 'min-height: 28rem'])
                                ->required($requiredWhenPublished),
                        ])->columnSpan(['lg' => 2]),

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
                                        ->required($requiredWhenPublished),
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
                                        ]),
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
                            Section::make('Oude afbeeldingen')
                                ->description('Geüpload met de vorige editor. De eerste wordt als omslagfoto gebruikt zolang er geen uitgelichte foto is.')
                                ->collapsed()
                                ->visible(fn (?Blog $record) => $record?->hasMedia('blog_attachments'))
                                ->schema([
                                    SpatieMediaLibraryFileUpload::make('attachments')
                                        ->hiddenLabel()
                                        ->collection('blog_attachments')
                                        ->multiple()
                                        ->reorderable(),
                                ]),
                        ])->columnSpan(['lg' => 1]),
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
            Grid::make(2)->schema([
                DatePicker::make('starts_on')
                    ->label('Van')
                    ->native(false)
                    ->displayFormat('d-m-Y'),
                DatePicker::make('ends_on')
                    ->label('Tot')
                    ->native(false)
                    ->displayFormat('d-m-Y')
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
                    // On a phone the title needs the room
                    ->visibleFrom('sm'),
                // On a phone: title, status, country and trip in one column, so the title has the width
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
                TextColumn::make('updated_at')
                    ->label('Laatst bewerkt')
                    ->since()
                    ->sortable()
                    ->visibleFrom('xl'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['countries', 'trip', 'featuredImage', 'media']))
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(self::STATUSES),
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
                // Icons, so the title keeps its room on phone and iPad (clicking the row also opens the editor)
                EditAction::make()->iconButton()->tooltip('Bewerken'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    // "Ingepland" for a published blog whose date is still to come
    private static function statusLabel(Blog $record): string
    {
        return $record->status === 'published' && ! $record->isPublished()
            ? 'Ingepland'
            : (self::STATUSES[$record->status] ?? $record->status);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogs::route('/'),
            'create' => CreateBlog::route('/create'),
            'edit' => EditBlog::route('/{record}/edit'),
        ];
    }
}
