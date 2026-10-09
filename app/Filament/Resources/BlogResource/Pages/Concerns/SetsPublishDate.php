<?php

namespace App\Filament\Resources\BlogResource\Pages\Concerns;

trait SetsPublishDate
{
    /**
     * Publishing without a date means "now", so the blog gets a real publish date
     * for sorting and display instead of falling back to when the draft was started.
     */
    protected function setPublishDate(array $data): array
    {
        if (($data['status'] ?? null) === 'published' && blank($data['published_at'] ?? null)) {
            $data['published_at'] = now();
        }

        return $data;
    }
}
