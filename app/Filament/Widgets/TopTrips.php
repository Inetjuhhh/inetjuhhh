<?php

namespace App\Filament\Widgets;

use App\Support\Statistics;
use Filament\Widgets\Widget;

/**
 * The biggest trips: the most blogs, with how often they were read and liked together (all time).
 */
class TopTrips extends Widget
{
    protected static ?int $sort = 8;

    protected string $view = 'filament.widgets.top-trips';

    protected function getViewData(): array
    {
        return ['trips' => Statistics::trips()->take(8)];
    }
}
