<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\Field;

/**
 * A small map in the form to place the blog's pin by hand: click the map or drag the pin.
 * It has no value of its own; it reads and writes the `latitude` and `longitude` fields
 * next to it, so whatever the map shows is exactly what gets saved.
 */
class LocationPicker extends Field
{
    protected string $view = 'filament.forms.location-picker';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dehydrated(false);
    }

    public function getSiblingStatePath(string $name): string
    {
        return str($this->getStatePath())->beforeLast('.')->append('.', $name)->toString();
    }
}
