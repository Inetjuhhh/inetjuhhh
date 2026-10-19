<?php

namespace App\Filament\Pages;

use App\Support\Avatar;
use Awcodes\Curator\Components\Forms\CuratorPicker;
use Awcodes\Curator\Models\Media;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use RuntimeException;

/**
 * Settings of the whole site. For now: the avatar (browser tab, home screen icon, menu, footer, replies).
 */
class EditSiteSettings extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string | \UnitEnum | null $navigationGroup = 'Beheer';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Website Instellingen';

    protected static ?string $title = 'Website Instellingen';

    protected static ?string $slug = 'instellingen';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['avatar' => Avatar::mediaId()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Avatar')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->description('Je avatar staat in het tabblad van de browser (site en admin), als icoon op het beginscherm van een telefoon, linksboven in het menu, in de footer en bij je antwoorden onder reacties.')
                    ->schema([
                        Text::make(fn () => new HtmlString(
                            '<span style="display:flex;align-items:center;gap:.75rem">Nu: '
                            . '<img src="' . e(Avatar::url(180)) . '" alt="" style="width:3rem;height:3rem;border-radius:9999px">'
                            . '<img src="' . e(Avatar::url(32)) . '" alt="" style="width:16px;height:16px"> (in het tabblad)</span>'
                        )),
                        CuratorPicker::make('avatar')
                            ->label('Kies een andere foto')
                            ->buttonLabel('Foto kiezen of uploaden')
                            ->helperText('Een vierkante foto (JPG, PNG of WebP) werkt het best. Een andere vorm wordt in het midden vierkant gemaakt. Leeg laten en opslaan zet de oorspronkelijke avatar terug.'),
                    ]),
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
                ]),
            ]);
    }

    public function save(): void
    {
        $mediaId = $this->form->getState()['avatar'] ?? null;

        // Nothing chosen, and nothing was: nothing to do. (The same photo again does make the icons
        // anew, so an improvement in how they are made reaches an avatar chosen earlier.)
        if (! $mediaId && ! Avatar::mediaId()) {
            Notification::make()->success()->title('Opgeslagen')->send();

            return;
        }

        if (! $mediaId) {
            Avatar::reset();
            Notification::make()->success()->title('De oorspronkelijke avatar staat weer terug')->send();

            return;
        }

        try {
            Avatar::useMedia(Media::findOrFail($mediaId));
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title('Niet gelukt')->body($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title('Je nieuwe avatar staat erop')
            ->body('Zie je in een tabblad nog de oude? Ververs de pagina met Ctrl+F5.')
            ->send();
    }
}
