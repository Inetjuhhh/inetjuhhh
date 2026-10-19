<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\Field;

/**
 * A small map in the form to place a pin by hand: click the map or drag the pin. It has no value
 * of its own; it reads and writes the coordinate fields next to it (latitude/longitude for a blog,
 * lat/lng for a stop of a planned route), so whatever the map shows is exactly what gets saved.
 */
class LocationPicker extends Field
{
    protected string $view = 'filament.forms.location-picker';

    protected string $latitudeField = 'latitude';

    protected string $longitudeField = 'longitude';

    /** @var array{0: string, 1: string}|null [field that remembers for which name the pin is, the name field] */
    protected ?array $placedFor = null;

    protected string $emptyText = 'Klik op de kaart om zelf een pin te zetten. Zonder pin komt de blog in het midden van het land.';

    protected string $height = 'h-72';

    protected bool $startsNearOtherItems = false;

    protected bool $compact = false;

    // How far the map zooms in on a pin: 10 shows a city, 6 or 7 a region with the places around it
    protected int $zoom = 10;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dehydrated(false);
    }

    public function coordinates(string $latitude, string $longitude): static
    {
        $this->latitudeField = $latitude;
        $this->longitudeField = $longitude;

        return $this;
    }

    /**
     * A pin placed by hand belongs to the name typed next to it: the field $placedFor gets that name,
     * so saving keeps the pin instead of looking the name up again (Trip::booted).
     */
    public function remembersPlaceIn(string $placedFor, string $nameField): static
    {
        $this->placedFor = [$placedFor, $nameField];

        return $this;
    }

    /**
     * In a repeater (the stops of a route): without a pin, the map starts at the closest item that
     * has one (the one before it, else the one after it), instead of the whole world.
     */
    public function startsNearOtherItems(bool $condition = true): static
    {
        $this->startsNearOtherItems = $condition;

        return $this;
    }

    /** @return array{list: string, item: string}|null the state path of the repeater and the key of this item */
    public function getRepeaterPaths(): ?array
    {
        if (! $this->startsNearOtherItems) {
            return null;
        }

        $item = str($this->getStatePath())->beforeLast('.');

        return ['list' => $item->beforeLast('.')->toString(), 'item' => $item->afterLast('.')->toString()];
    }

    public function getLatitudeField(): string
    {
        return $this->latitudeField;
    }

    public function getLongitudeField(): string
    {
        return $this->longitudeField;
    }

    /**
     * A small map next to a field: a low map and one short line under it (coordinates, ✕ pin).
     */
    public function compact(bool $condition = true): static
    {
        $this->compact = $condition;

        return $this;
    }

    public function isCompact(): bool
    {
        return $this->compact;
    }

    public function zoom(int $level): static
    {
        $this->zoom = $level;

        return $this;
    }

    public function getZoom(): int
    {
        return $this->zoom;
    }

    public function emptyText(string $text): static
    {
        $this->emptyText = $text;

        return $this;
    }

    public function mapHeight(string $tailwindHeight): static
    {
        $this->height = $tailwindHeight;

        return $this;
    }

    public function getSiblingStatePath(string $name): string
    {
        return str($this->getStatePath())->beforeLast('.')->append('.', $name)->toString();
    }

    public function getLatitudePath(): string
    {
        return $this->getSiblingStatePath($this->latitudeField);
    }

    public function getLongitudePath(): string
    {
        return $this->getSiblingStatePath($this->longitudeField);
    }

    /** @return array{0: string, 1: string}|null the state paths of [placed for, name] */
    public function getPlacedForPaths(): ?array
    {
        return $this->placedFor
            ? [$this->getSiblingStatePath($this->placedFor[0]), $this->getSiblingStatePath($this->placedFor[1])]
            : null;
    }

    public function getEmptyText(): string
    {
        return $this->emptyText;
    }

    public function getMapHeight(): string
    {
        return $this->height;
    }
}
