<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ResponseResource\Pages\EditResponse;
use App\Filament\Resources\ResponseResource\Pages\ListResponses;
use App\Models\Response;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Comments from readers: approve, mark as spam, or reply as the blog owner.
 */
class ResponseResource extends Resource
{
    protected static ?string $model = Response::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string | \UnitEnum | null $navigationGroup = 'Lezers';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'reactie';

    protected static ?string $pluralModelLabel = 'reacties';


    // Waiting comments, shown as a counter in the menu
    public static function getNavigationBadge(): ?string
    {
        $waiting = Response::pending()->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Wachten op goedkeuring';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Naam')->required()->maxLength(80),
            TextInput::make('email')->label('E-mailadres')->email()->required()->disabled(fn (?Response $record) => $record?->is_author),
            Textarea::make('response')->label('Reactie')->required()->rows(6)->columnSpanFull(),
            ToggleButtons::make('status')
                ->label('Status')
                ->options(Response::STATUSES)
                ->colors([Response::PENDING => 'warning', Response::APPROVED => 'success', Response::SPAM => 'danger'])
                ->inline()
                ->required(),
            Toggle::make('notify_on_reply')
                ->label('Wil een mail bij een antwoord')
                ->visible(fn (?Response $record) => ! $record?->is_author),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Replies by the blog owner are shown under the comment they answer, not as rows
            ->modifyQueryUsing(fn (Builder $query) => $query->where('is_author', false)->with(['blog', 'replies']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                // On a phone: who and what in one column, so the comment isn't cut off at the edge
                TextColumn::make('comment_on_phone')
                    ->label('Reactie')
                    ->state(fn (Response $record) => $record->response)
                    ->limit(140)
                    ->wrap()
                    ->description(fn (Response $record) => collect([
                        $record->name,
                        $record->created_at->diffForHumans(),
                        $record->status !== Response::APPROVED ? Response::STATUSES[$record->status] ?? null : null,
                    ])->filter()->join(' · '), position: 'above')
                    ->hiddenFrom('sm'),
                TextColumn::make('name')
                    ->label('Van')
                    ->description(fn (Response $record) => $record->email)
                    ->searchable(['name', 'email'])
                    ->visibleFrom('sm'),
                TextColumn::make('response')
                    ->label('Reactie')
                    ->visibleFrom('sm')
                    ->limit(160)
                    ->wrap()
                    ->grow()
                    ->extraAttributes(['class' => 'min-w-64'])
                    ->description(fn (Response $record) => $record->replies->isNotEmpty()
                        ? '↳ Je antwoordde: '.str($record->replies->last()->response)->limit(80)
                        : null)
                    ->searchable(),
                TextColumn::make('blog.title')
                    ->label('Bij')
                    ->limit(28)
                    ->url(fn (Response $record) => $record->blog?->isPublished()
                        ? route('blogs.show', $record->blog).'#reactie-'.$record->id
                        : null)
                    ->openUrlInNewTab()
                    ->visibleFrom('xl'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Response::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        Response::APPROVED => 'success',
                        Response::SPAM => 'danger',
                        default => 'warning',
                    })
                    ->visibleFrom('sm'),
                TextColumn::make('created_at')
                    ->label('Geplaatst')
                    ->since()
                    ->sortable()
                    ->visibleFrom('xl'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(Response::STATUSES),
                SelectFilter::make('blog')
                    ->label('Blog')
                    ->relationship('blog', 'title')
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Goedkeuren')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (Response $record) => $record->status !== Response::APPROVED)
                    ->action(fn (Response $record) => $record->approve())
                    // A button with text on a computer, just the tick on phone and iPad
                    ->button()
                    ->size('sm')
                    ->labeledFrom('xl'),
                // Less used actions in a menu, so the comment itself gets the room
                ActionGroup::make([
                    Action::make('reply')
                        ->label('Antwoorden')
                        ->icon(Heroicon::OutlinedChatBubbleLeft)
                        ->color('gray')
                        ->modalHeading(fn (Response $record) => 'Antwoord aan '.$record->name)
                        ->modalDescription(fn (Response $record) => str($record->response)->limit(300))
                        ->schema([
                            Textarea::make('response')->label('Je antwoord')->required()->rows(5),
                        ])
                        ->action(function (Response $record, array $data) {
                            // Replying to a comment also approves it
                            $record->approve();

                            $record->replies()->create([
                                'blog_id' => $record->blog_id,
                                'name' => auth()->user()->name,
                                'email' => auth()->user()->email,
                                'response' => $data['response'],
                                'is_author' => true,
                                'status' => Response::APPROVED,
                                'approved_at' => now(),
                            ]);

                            Notification::make()
                                ->title($record->notify_on_reply ? 'Antwoord geplaatst, '.$record->name.' krijgt een mailtje' : 'Antwoord geplaatst')
                                ->success()
                                ->send();
                        }),
                    Action::make('spam')
                        ->label('Spam')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('danger')
                        ->visible(fn (Response $record) => $record->status !== Response::SPAM)
                        ->action(fn (Response $record) => $record->markAsSpam()),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label('Goedkeuren')
                        ->icon(Heroicon::OutlinedCheck)
                        ->action(fn (Collection $records) => $records->each->approve())
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('spam')
                        ->label('Markeer als spam')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('danger')
                        ->action(fn (Collection $records) => $records->each->markAsSpam())
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListResponses::route('/'),
            'edit' => EditResponse::route('/{record}/edit'),
        ];
    }
}
