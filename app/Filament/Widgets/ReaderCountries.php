<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasPeriod;
use App\Support\Statistics;
use Filament\Widgets\Widget;

/**
 * Where readers live: per country, and a click further per city (since the new counting).
 */
class ReaderCountries extends Widget
{
    use HasPeriod;

    protected static ?int $sort = 6;

    protected string $view = 'filament.widgets.reader-countries';

    protected function getViewData(): array
    {
        return ['countries' => Statistics::countries($this->period)];
    }
}
