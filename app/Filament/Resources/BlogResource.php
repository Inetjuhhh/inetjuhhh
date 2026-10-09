<?php

namespace App\Filament\Resources;

use App\Filament\Blocks\BlogBlock;
use App\Filament\Resources\BlogResource\Pages\CreateBlog;
use App\Filament\Resources\BlogResource\Pages\EditBlog;
use App\Filament\Resources\BlogResource\Pages\ListBlogs;
use App\Models\Blog;
use Awcodes\Curator\Components\Forms\CuratorPicker;
use Awcodes\Curator\Components\Forms\RichEditor\AttachCuratorMediaPlugin;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
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
                                        ->unique(ignoreRecord: true)
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
                                        ->relationship('featuredImage', 'id')
                                ]),
                            Section::make('Waar gaat het over?')
                                ->icon(Heroicon::OutlinedMapPin)
                                ->schema([
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover')
                    ->label('')
                    ->state(fn (Blog $record) => $record->coverUrl(160, 120))
                    ->imageWidth(64)
                    ->imageHeight(48),
                TextColumn::make('title')
                    ->label('Titel')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->description(fn (Blog $record) => $record->countries->pluck('name')->join(', ')),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (Blog $record, string $state) => $state === 'published' && ! $record->isPublished()
                        ? 'Ingepland'
                        : (self::STATUSES[$state] ?? $state))
                    ->color(fn (Blog $record, string $state) => match (true) {
                        $state === 'published' && ! $record->isPublished() => 'info',
                        $state === 'published' => 'success',
                        $state === 'archived' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
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
                    ->visibleFrom('lg'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['countries', 'featuredImage', 'media']))
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(self::STATUSES),
                SelectFilter::make('countries')
                    ->label('Land')
                    ->relationship('countries', 'name')
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
                    ->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
