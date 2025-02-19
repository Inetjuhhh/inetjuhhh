<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlogResource\Pages;
use App\Filament\Resources\BlogResource\RelationManagers;
use App\Models\Blog;
use App\Models\Tag;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BlogResource extends Resource
{
    protected static ?string $model = Blog::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                        \App\Models\Country::all()->pluck('name', 'id')
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
                        \App\Models\Category::all()->pluck('name', 'id')
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
                RichEditor::make('content')
                    ->label('Content')
                    ->columnSpanFull()
                    ->required(),
                SpatieMediaLibraryFileUpload::make('attachments')
                    ->label('Images')
                    ->preserveFilenames()
                    ->collection('blog_attachments')
                    ->columnSpanFull()
                    ->multiple(),
                TextInput::make('status')
                    ->label('Status')
                    ->default('draft')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListBlogs::route('/'),
            'create' => Pages\CreateBlog::route('/create'),
            'edit' => Pages\EditBlog::route('/{record}/edit'),
        ];
    }
}
