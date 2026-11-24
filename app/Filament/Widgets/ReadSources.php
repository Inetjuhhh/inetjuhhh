<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasPeriod;
use App\Support\Statistics;
use Filament\Widgets\Widget;

/**
 * How readers came to a story: Google, Facebook, the newsletter, ... (since the new counting).
 * Shows what works to bring readers in.
 */
class ReadSources extends Widget
{
    use HasPeriod;

    protected static ?int $sort = 7;

    protected string $view = 'filament.widgets.read-sources';

    protected function getViewData(): array
    {
        return ['sources' => Statistics::sources($this->period)];
    }
}
