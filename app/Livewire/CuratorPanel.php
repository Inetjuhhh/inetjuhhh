<?php

namespace App\Livewire;

use App\Filament\Curator\MediaTable;
use App\Support\PhotoFingerprint;
use App\Support\PhotoTrips;
use Awcodes\Curator\Components\Modals\CuratorPanel as BaseCuratorPanel;
use Awcodes\Curator\Models\Media;
use Awcodes\Curator\Support\MediaScope;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Curator's media window, plus a question when a photo being added is already in the library:
 * add it anyway (it gets its own name, "IMG_1234 (1)"), or use the photo that is already there.
 *
 * Many photos at once are added a few at a time, each batch in its own request: every photo is
 * shrunk and compared on the server, and doing them all in one request ran the server out of time
 * and memory. The duplicate check already happens while the photos upload, one by one.
 */
class CuratorPanel extends BaseCuratorPanel
{
    // Photos stored per request; one takes a few seconds and a lot of memory while it is shrunk
    public const BATCH_SIZE = 5;

    // Duplicate checks per request while uploading (anything left over is checked on the click)
    public const CHECKS_PER_REQUEST = 2;

    public const PROGRESS_NOTIFICATION = 'curator-upload-progress';

    /** @var array<string, Collection<int, Media>>|null Upload key → the same photo in the library, per request */
    protected ?array $duplicates = null;

    /** @var array<string, list<int|string>> Temporary file name → ids of the same photo in the library */
    #[Locked]
    public array $duplicateIds = [];

    /**
     * Photos still to be added after the click: the upload keys in line, the photos already in the
     * library chosen instead, what has been added so far, and whether to insert them at the end.
     *
     * @var array{keys?: list<string>, existing?: list<array>, added?: list<array>, total?: int, insertAfter?: bool}
     */
    #[Locked]
    public array $uploadQueue = [];

    /**
     * The trip the panel starts on: the trip of the blog being edited, or the trip whose cover is
     * being chosen (passed with the settings, see resources/views/vendor/curator/components/modals).
     */
    #[Locked]
    public int | string | null $tripId = null;

    public function mount(): void
    {
        parent::mount();

        $this->form->fill(['trip_id' => $this->tripId]);
    }

    /**
     * Above the upload field: the trip whose photos are shown, which new photos are linked to as well.
     */
    public function form(Schema $schema): Schema
    {
        $schema = parent::form($schema);

        return $schema->components([
            Select::make('trip_id')
                ->label('Reis')
                ->placeholder('Alle foto\'s')
                ->options(fn () => PhotoTrips::options())
                ->searchable()
                ->live()
                ->helperText('Toont de foto\'s van deze reis, en nieuwe foto\'s worden eraan gekoppeld.'),
            ...$schema->getComponents(withHidden: true),
        ]);
    }

    public function selectedTrip(): int | string | null
    {
        return array_key_exists('trip_id', $this->panelData ?? []) ? $this->panelData['trip_id'] : $this->tripId;
    }

    /**
     * Curator's list of photos, of the chosen trip only.
     */
    public function getFiles(int $page = 0, bool $excludeSelected = false): array
    {
        $files = $this->tripQuery()
            ->where(fn (Builder $query): Builder => filled($this->directory)
                ? MediaScope::whereWithinDirectory($query, $this->directory)
                : $query->whereNull('directory'))
            ->tap(fn (Builder $query) => $this->orderPhotos($query))
            ->orderBy('created_at', $this->defaultSort);

        $paginator = $files->paginate($this->defaultLimit, page: $page);

        $this->currentPage = $paginator->currentPage();
        $this->mediaCount = $paginator->total();
        $this->lastPage = $paginator->lastPage();

        $this->getSubDirectories();
        $this->getBreadCrumbs();

        return collect($paginator->items())->map(fn ($item) => $item->toArray())->toArray();
    }

    /**
     * Curator's search, within the chosen trip too.
     */
    public function updatedSearch(): void
    {
        $terms = $this->getSearchTerms();

        if ($terms === []) {
            $this->files = $this->getFiles();

            return;
        }

        $this->files = $this->tripQuery()
            ->where(function ($query) use ($terms): void {
                foreach ($terms as $term) {
                    $query->where(function ($query) use ($term): void {
                        foreach (static::SEARCH_COLUMNS as $column) {
                            $query->orWhereRaw($query->getGrammar()->wrap($column) . " like ? escape '~'", ['%' . $term . '%']);
                        }
                    });
                }
            })
            ->tap(fn (Builder $query) => $this->orderPhotos($query))
            ->orderBy('created_at', $this->defaultSort)
            ->limit(50)
            ->get()
            ->toArray();

        $this->currentPage = $this->lastPage;
    }

    protected function tripQuery(): Builder
    {
        $query = $this->getMediaScope()->query();

        return filled($trip = $this->selectedTrip()) ? PhotoTrips::scope($query, $trip) : $query;
    }

    /**
     * The order (the newest first within each part): the photos of the trip that are used nowhere yet,
     * the rest of the trip, then all others. The trip is the chosen one, or with "all photos" still the
     * blog's own trip. Without any trip: the photos used nowhere first.
     */
    protected function orderPhotos(Builder $query): Builder
    {
        $trip = $this->selectedTrip() ?: $this->tripId;

        return filled($trip) ? PhotoTrips::orderTripFirst($query, $trip) : MediaTable::orderUnusedFirst($query);
    }

    /**
     * Another trip chosen: its photos. And each photo that finishes uploading is compared with the
     * library right away, so the click on "add" doesn't have to compare them all at once.
     */
    public function updatedPanelData(mixed $value = null, ?string $key = null): void
    {
        if ($key === 'trip_id') {
            $this->updatedSearch();

            return;
        }

        if ($this->uploadQueue !== [] || ($key !== null && ! str_starts_with($key, 'files_to_add'))) {
            return;
        }

        $unchecked = collect($this->panelData['files_to_add'] ?? [])
            ->filter(fn (mixed $file): bool => $file instanceof TemporaryUploadedFile
                && ! array_key_exists($file->getFilename(), $this->duplicateIds))
            ->take(self::CHECKS_PER_REQUEST);

        foreach ($unchecked as $file) {
            $this->duplicateIds[$file->getFilename()] = $this->findDuplicates($file)->map(fn (Media $media) => $media->getKey())->all();
        }
    }

    public function addFilesAction(bool $insertAfter = false): Action
    {
        return parent::addFilesAction($insertAfter)
            ->requiresConfirmation()
            // Not again while earlier photos are still being added
            ->disabled(fn (): bool => $this->uploadQueue !== [] || count($this->form->getRawState()['files_to_add'] ?? []) === 0)
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
                if ($this->uploadQueue !== []) {
                    return;
                }

                // Instead of a new copy: the photo that is already there
                $existing = [];
                $skip = [];
                if ($arguments['useExisting'] ?? false) {
                    foreach ($this->uploadDuplicates() as $key => $matches) {
                        $skip[] = $key;
                        $existing[] = $matches->first()->toArray();
                    }
                }

                $keys = collect($this->panelData['files_to_add'] ?? [])
                    ->filter(fn (mixed $file): bool => $file instanceof TemporaryUploadedFile)
                    ->keys()
                    ->diff($skip)
                    ->values()
                    ->all();

                $this->uploadQueue = [
                    'keys' => $keys,
                    'existing' => $existing,
                    'added' => [],
                    'total' => count($keys),
                    'insertAfter' => $insertAfter,
                ];

                if (count($keys) > self::BATCH_SIZE) {
                    Notification::make(self::PROGRESS_NOTIFICATION)
                        ->info()
                        ->title(count($keys) . " foto's toevoegen…")
                        ->body('Ze worden met een paar tegelijk verkleind en opgeslagen. Laat dit venster open tot het klaar is.')
                        ->persistent()
                        ->send();
                }

                $this->addNextUploads();
            });
    }

    /**
     * Adds the next few photos in line, and asks the browser for another round until all are done.
     */
    public function addNextUploads(): void
    {
        if ($this->uploadQueue === []) {
            return;
        }

        $all = $this->panelData['files_to_add'] ?? [];
        $keys = array_splice($this->uploadQueue['keys'], 0, self::BATCH_SIZE);
        $batch = array_filter(
            array_intersect_key($all, array_flip($keys)),
            fn (mixed $file): bool => $file instanceof TemporaryUploadedFile,
        );

        $media = [];
        if ($batch !== []) {
            // Only this batch goes through the upload field; the others wait their turn
            $this->panelData['files_to_add'] = $batch;
            $media = $this->createMediaFiles();
            // Put back as it was, so the upload field in the browser doesn't reload all photos
            $this->panelData['files_to_add'] = $all;
        }

        $this->files = [...$media, ...$this->files];
        $this->uploadQueue['added'] = [...$this->uploadQueue['added'], ...$media];

        if ($this->uploadQueue['keys'] !== []) {
            $this->js('$wire.addNextUploads()');

            return;
        }

        $this->finishUploads();
    }

    protected function finishUploads(): void
    {
        ['added' => $added, 'existing' => $existing, 'total' => $total, 'insertAfter' => $insertAfter] = $this->uploadQueue;

        $this->uploadQueue = [];
        $this->duplicateIds = [];
        $this->duplicates = null;

        // An empty upload field again, on the same trip
        $this->form->fill(['trip_id' => $this->selectedTrip()]);

        $known = collect($this->files)->pluck('id')->all();
        $this->files = [
            ...array_filter($existing, fn (array $item): bool => ! in_array($item['id'], $known)),
            ...$this->files,
        ];

        $chosen = [...$added, ...$existing];

        // A single picker holds one item, so an upload replaces the selection rather than adding to it.
        $this->selected = $this->isMultiple
            ? [...$this->selected, ...$chosen]
            : array_slice($chosen, 0, 1);

        if ($total > self::BATCH_SIZE) {
            $this->dispatch('close-notification', id: self::PROGRESS_NOTIFICATION);

            $complete = count($added) === $total;
            Notification::make()
                ->status($complete ? 'success' : 'warning')
                ->title($complete ? "{$total} foto's toegevoegd" : count($added) . " van de {$total} foto's toegevoegd")
                ->send();
        }

        if ($insertAfter) {
            $this->dispatchInsertMedia();
        }
    }

    /**
     * New photos get a name of their own: "IMG_1234 (1)" when "IMG_1234" is already in the library,
     * and are linked to the chosen trip.
     */
    protected function createMediaFiles(): array
    {
        $trip = $this->selectedTrip();

        $media = $this->createNamedMediaFiles();

        if (filled($trip) && PhotoTrips::link(array_column($media, 'id'), $trip)) {
            $media = array_map(fn (array $item): array => [...$item, 'trip_id' => (int) $trip], $media);
        }

        return $media;
    }

    protected function createNamedMediaFiles(): array
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
            ->map(function (TemporaryUploadedFile $file): Collection {
                // Already compared while it uploaded (see updatedPanelData)
                $ids = $this->duplicateIds[$file->getFilename()] ?? null;

                if ($ids === null) {
                    return $this->findDuplicates($file);
                }

                $found = Media::findMany($ids)->keyBy(fn (Media $media) => $media->getKey());

                return collect($ids)->map(fn ($id) => $found->get($id))->filter()->values();
            })
            ->filter(fn (Collection $matches): bool => $matches->isNotEmpty())
            ->all();
    }

    /**
     * @return Collection<int, Media>
     */
    protected function findDuplicates(TemporaryUploadedFile $file): Collection
    {
        try {
            return PhotoFingerprint::matches((string) $file->get());
        } catch (Throwable) {
            return collect();
        }
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
