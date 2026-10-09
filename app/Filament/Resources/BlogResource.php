<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Models\Country;
use App\Models\Category;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\BlogResource\Pages\ListBlogs;
use App\Filament\Resources\BlogResource\Pages\CreateBlog;
use App\Filament\Resources\BlogResource\Pages\EditBlog;
use App\Filament\Resources\BlogResource\Pages;
use App\Filament\Resources\BlogResource\RelationManagers;
use App\Models\Blog;
use App\Models\Tag;
use DateTime;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use FilamentTiptapEditor\TiptapEditor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use FilamentTiptapEditor\Enums\TiptapOutput;

class BlogResource extends Resource
{
    protected static ?string $model = Blog::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Title')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        $set('slug', Str::slug($state));

                    }),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required(),
                TextInput::make('excerpt')
                    ->label('Excerpt')
                    ->default('No excerpt')	,
                Select::make('country_id')
                    ->label('Country')
                    ->relationship('countries', 'name')
                    ->multiple()
                    ->live(onBlur: true)
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Land')
                            ->required(),
                    ])
                    ->options(
                        Country::all()->pluck('name', 'id')
                    )
                    ->required(),
                Select::make('categories')
                    ->label('Category')
                    ->multiple()
                    ->preload()
                    ->relationship('categories', 'name')
                    ->live(onBlur: true)
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Categorie')
                            ->required(),
                    ])
                    ->options(
                        Category::all()->pluck('name', 'id')
                    )
                    ->afterStateUpdated(function(Get $get, Set $set, $state) {
                        $set('category_id', $state);

                    })
                    ->required(),
                // Select::make('subcategory_id')
                //     ->label('Subcategory')
                //     ->relationship('subcategory', 'name')
                //     ->live(onBlur: true)
                //     ->options(function(Get $get) {
                //         $category_id = $get('category_id');
                //         return \App\Models\Subcategory::where('category_id', $get('category_id'))->get()->pluck('name', 'id');
                //     }),
                // TagsInput::make('tags')
                //     ->label('Tags'),
                // Textarea::make('content')
                //     ->label('Content')
                //     ->required(),
                TiptapEditor::make('content')
                    ->output(TiptapOutput::Json)
                    ->profile('default')
                    ->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('attachments')
                    ->label('Images')
                    ->preserveFilenames()
                    ->collection('blog_attachments')
                    ->columnSpanFull()
                    ->multiple(),
                Select::make('status')
                    ->label('Status')
                    ->default('draft')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ])
                    ->required(),
                DateTimePicker::make('published_at')
                    ->label('Published at')
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->searchable()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
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
