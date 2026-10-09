<?php

namespace App\Filament\Resources\BlogResource\Pages;

use App\Filament\Resources\BlogResource;
use App\Filament\Resources\BlogResource\Pages\Concerns\AutosavesDrafts;
use App\Filament\Resources\BlogResource\Pages\Concerns\SetsPublishDate;
use App\Models\Blog;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;

class CreateBlog extends CreateRecord
{
    use AutosavesDrafts;
    use SetsPublishDate;

    protected static string $resource = BlogResource::class;

    // The draft created by the first autosave; later autosaves and "Create" update it
    #[Locked]
    public ?int $autosavedRecordId = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['placed_by_id'] = auth()->id();

        return $this->setPublishDate($data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        if ($record = $this->autosavedRecord()) {
            $record->update($data);

            return $record;
        }

        return parent::handleRecordCreation($data);
    }

    protected function isDraft(): bool
    {
        return true;
    }

    protected function persistAutosave(): void
    {
        DB::transaction(function () {
            $data = $this->mutateFormDataBeforeCreate($this->form->getState());

            $record = $this->handleRecordCreation($data);
            $this->autosavedRecordId = $record->getKey();

            $this->form->model($record)->saveRelationships();
        });
    }

    public function autosavedRecord(): ?Blog
    {
        return $this->autosavedRecordId ? Blog::find($this->autosavedRecordId) : null;
    }
}
