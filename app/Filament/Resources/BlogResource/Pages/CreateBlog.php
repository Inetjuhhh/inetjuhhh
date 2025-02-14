<?php

namespace App\Filament\Resources\BlogResource\Pages;

use App\Filament\Resources\BlogResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateBlog extends CreateRecord
{
    protected static string $resource = BlogResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['placed_by'] = auth()->id();
        return $data;
    }

    // protected function afterCreate(): void
    // {
    //     $blog = $this->record;
    //     dd($blog);
    // }
}
