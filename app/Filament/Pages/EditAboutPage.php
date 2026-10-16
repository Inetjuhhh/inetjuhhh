<?php

namespace App\Filament\Pages;

use App\Filament\Forms\StoryEditor;
use App\Models\Page as SitePage;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The "Ik & wij" page: one fixed page, so there is nothing to add or delete, only its text to edit.
 */
class EditAboutPage extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Ik & wij';

    protected static ?string $title = 'Ik & wij';

    protected static ?string $slug = 'ik-en-wij';

    protected static ?int $navigationSort = 2;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(SitePage::about()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model(SitePage::about())
            ->statePath('data')
            ->components([
                TextInput::make('title')
                    ->label('Titel')
                    ->required()
                    ->maxLength(255),
                StoryEditor::make('content')
                    ->label('Tekst'),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->getFormContentComponent()]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')->label('Opslaan')->submit('save')->keyBindings(['mod+s']),
                    Action::make('view')->label('Bekijk de pagina')->color('gray')
                        ->url(route('about'), shouldOpenInNewTab: true),
                ]),
            ]);
    }

    public function save(): void
    {
        SitePage::about()->update($this->form->getState());

        Notification::make()->success()->title('Opgeslagen')->send();
    }
}
