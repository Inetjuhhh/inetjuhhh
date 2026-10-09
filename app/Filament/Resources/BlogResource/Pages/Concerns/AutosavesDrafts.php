<?php

namespace App\Filament\Resources\BlogResource\Pages\Concerns;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;

/**
 * Saves drafts in the background every 30 seconds (see filament.blogs.autosave), so a bad
 * hotel wifi connection never costs you a story. Published blogs are never autosaved:
 * changes to those only go live when you press save yourself.
 */
trait AutosavesDrafts
{
    public ?string $autosavedAt = null;

    public ?string $autosavedHash = null;

    abstract protected function persistAutosave(): void;

    abstract protected function isDraft(): bool;

    public function autosave(): void
    {
        if (! $this->canAutosave()) {
            return;
        }

        $hash = md5(json_encode($this->data));

        if ($hash === $this->autosavedHash) {
            return;
        }

        try {
            $this->persistAutosave();
        } catch (ValidationException) {
            // Something isn't valid yet (e.g. a duplicate link); the next save will tell you
            $this->resetErrorBag();

            return;
        }

        $this->autosavedHash = $hash;
        $this->autosavedAt = now()->format('H:i');
    }

    public function canAutosave(): bool
    {
        return $this->isDraft()
            && ($this->data['status'] ?? 'draft') === 'draft'
            && filled($this->data['title'] ?? null)
            && filled($this->data['slug'] ?? null);
    }

    public function getFooter(): ?View
    {
        return view('filament.blogs.autosave');
    }
}
