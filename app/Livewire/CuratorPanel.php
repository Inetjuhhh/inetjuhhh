<?php

namespace App\Livewire;

use App\Support\PhotoFingerprint;
use Awcodes\Curator\Components\Modals\CuratorPanel as BaseCuratorPanel;
use Awcodes\Curator\Models\Media;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Curator's media window, plus a question when a photo being added is already in the library:
 * add it anyway (it gets its own name, "IMG_1234 (1)"), or use the photo that is already there.
 */
class CuratorPanel extends BaseCuratorPanel
{
    /** @var array<string, Collection<int, Media>>|null Upload key → the same photo in the library, per request */
    protected ?array $duplicates = null;

    public function addFilesAction(bool $insertAfter = false): Action
    {
        return parent::addFilesAction($insertAfter)
            ->requiresConfirmation()
            // Only a question when there is a match; a new photo is added straight away
            ->modalHidden(fn (): bool => $this->uploadDuplicates() === [])
            ->modalIcon(Heroicon::OutlinedPhoto)
            ->modalHeading(fn (): string => count($this->uploadDuplicates()) === 1
                ? 'Deze foto staat al in je bibliotheek'
                : count($this->uploadDuplicates()) . " foto's staan al in je bibliotheek")
            ->modalDescription(null)
            ->modalContent(fn () => view('filament.curator.duplicates', ['rows' => $this->duplicateRows()]))
            ->modalSubmitActionLabel('Toch toevoegen')
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('useExisting', arguments: ['useExisting' => true])
                    ->label('Gebruik de bestaande')
                    ->color('gray'),
            ])
            ->action(function (array $arguments) use ($insertAfter): void {
                // Instead of a new copy: the photo that is already there
                $existing = [];
                if ($arguments['useExisting'] ?? false) {
                    foreach ($this->uploadDuplicates() as $key => $matches) {
                        unset($this->panelData['files_to_add'][$key]);
                        $existing[] = $matches->first()->toArray();
                    }
                }

                $media = $this->createMediaFiles();

                $this->form->fill();

                $known = collect($this->files)->pluck('id')->all();
                $this->files = [
                    ...$media,
                    ...array_filter($existing, fn (array $item): bool => ! in_array($item['id'], $known)),
                    ...$this->files,
                ];

                $chosen = [...$media, ...$existing];

                // A single picker holds one item, so an upload replaces the selection rather than adding to it.
                $this->selected = $this->isMultiple
                    ? [...$this->selected, ...$chosen]
                    : array_slice($chosen, 0, 1);

                if ($insertAfter) {
                    $this->dispatchInsertMedia();
                }
            });
    }

    /**
     * New photos get a name of their own: "IMG_1234 (1)" when "IMG_1234" is already in the library.
     */
    protected function createMediaFiles(): array
    {
        return array_map(function (array $item): array {
            $media = Media::find($item['id']);
            $title = static::uniqueTitle((string) $media?->title, $media?->getKey());

            if (! $media || $title === $media->title) {
                return $item;
            }

            $media->update(['title' => $title]);

            return $media->toArray();
        }, parent::createMediaFiles());
    }

    public static function uniqueTitle(string $title, int | string | null $exceptId = null): string
    {
        $taken = fn (string $candidate): bool => Media::where('title', $candidate)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($title === '' || ! $taken($title)) {
            return $title;
        }

        $number = 1;
        while ($taken("{$title} ({$number})")) {
            $number++;
        }

        return "{$title} ({$number})";
    }

    /**
     * @return array<string, Collection<int, Media>>
     */
    protected function uploadDuplicates(): array
    {
        return $this->duplicates ??= collect($this->panelData['files_to_add'] ?? [])
            ->filter(fn (mixed $file): bool => $file instanceof TemporaryUploadedFile)
            ->map(fn (TemporaryUploadedFile $file) => PhotoFingerprint::matches((string) $file->get()))
            ->filter(fn (Collection $matches): bool => $matches->isNotEmpty())
            ->all();
    }

    /**
     * What the question shows: per photo its name, the name it would get, and the photo already there.
     *
     * @return list<array{name: string, newName: string, existing: Media}>
     */
    protected function duplicateRows(): array
    {
        return collect($this->uploadDuplicates())->map(function (Collection $matches, string $key): array {
            $name = pathinfo($this->panelData['files_to_add'][$key]->getClientOriginalName(), PATHINFO_FILENAME);

            return ['name' => $name, 'newName' => static::uniqueTitle($name), 'existing' => $matches->first()];
        })->values()->all();
    }
}
