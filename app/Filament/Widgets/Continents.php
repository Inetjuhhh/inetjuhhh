<?php

namespace App\Filament\Widgets;

use App\Support\Statistics;
use Filament\Widgets\Widget;

/**
 * Per continent: how many blogs are about it, and how often those were read (all time).
 */
class Continents extends Widget
{
    protected static ?int $sort = 9;

    protected string $view = 'filament.widgets.continents';

    protected function getViewData(): array
    {
        return [
            'continents' => Statistics::continents(),
            'missing' => \App\Models\Country::whereNull('continent')->whereHas('blogs')->pluck('name'),
        ];
    }
}
