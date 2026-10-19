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

/**
 * A fixed page of the site (Ik & wij, Privacy): there is nothing to add or delete, only its text to edit.
 */
abstract class EditSitePage extends Page
{
    /** The page record this admin page edits */
    abstract protected static function sitePage(): SitePage;

    /** Name of the route that shows the page on the site */
    abstract protected static function siteRoute(): string;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(static::sitePage()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model(static::sitePage())
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
                        ->url(route(static::siteRoute()), shouldOpenInNewTab: true),
                ]),
            ]);
    }

    public function save(): void
    {
        static::sitePage()->update($this->form->getState());

        Notification::make()->success()->title('Opgeslagen')->send();
    }
}
