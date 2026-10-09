<?php

namespace App\Filament\Resources\BlogResource\Pages;

use App\Filament\Resources\BlogResource;
use App\Filament\Resources\BlogResource\Pages\Concerns\AutosavesDrafts;
use App\Filament\Resources\BlogResource\Pages\Concerns\SetsPublishDate;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditBlog extends EditRecord
{
    use AutosavesDrafts;
    use SetsPublishDate;

    protected static string $resource = BlogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label(fn () => $this->getRecord()->isPublished() ? 'Bekijk op de site' : 'Voorbeeld')
                ->tooltip('Toont de laatst opgeslagen versie')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn () => $this->getRecord()->isPublished()
                    ? route('blogs.show', $this->getRecord())
                    : $this->getRecord()->previewUrl())
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->setPublishDate($data);
    }

    protected function isDraft(): bool
    {
        return $this->getRecord()->status === 'draft';
    }

    protected function persistAutosave(): void
    {
        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
    }
}
