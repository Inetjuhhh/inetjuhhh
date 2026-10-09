<?php

namespace App\Filament\Resources\BlogResource\Pages;

use App\Filament\Resources\BlogResource;
use App\Filament\Resources\BlogResource\Pages\Concerns\SetsPublishDate;
use Filament\Resources\Pages\CreateRecord;

class CreateBlog extends CreateRecord
{
    use SetsPublishDate;

    protected static string $resource = BlogResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['placed_by_id'] = auth()->id();

        return $this->setPublishDate($data);
    }
}
