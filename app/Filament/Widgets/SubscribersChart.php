<?php

namespace App\Filament\Widgets;

use App\Support\Statistics;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * How the newsletter grows: confirmed subscribers at the end of each month.
 */
class SubscribersChart extends ChartWidget
{
    protected static ?int $sort = 10;

    protected ?string $heading = 'Abonnees op de nieuwsbrief';

    protected ?string $description = 'Het totaal aan het eind van elke maand';

    protected ?string $maxHeight = '220px';

    protected function getData(): array
    {
        $months = Statistics::subscribersPerMonth();

        return [
            'datasets' => [[
                'label' => 'Abonnees',
                'data' => array_values($months),
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => array_map(fn ($month) => Carbon::parse($month . '-01')->translatedFormat('M'), array_keys($months)),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
