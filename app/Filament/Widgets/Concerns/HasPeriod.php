<?php

namespace App\Filament\Widgets\Concerns;

use App\Support\Statistics;

/**
 * A widget with "30 dagen / Jaar / Altijd" above it (resources/views/filament/widgets/partials/period).
 */
trait HasPeriod
{
    public string $period = '30';

    public function updatedPeriod(): void
    {
        if (! array_key_exists($this->period, Statistics::PERIODS)) {
            $this->period = '30';
        }
    }
}
