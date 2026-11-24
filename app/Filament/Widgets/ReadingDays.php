<?php

namespace App\Filament\Widgets;

use App\Support\Statistics;
use Filament\Widgets\ChartWidget;

/**
 * On which days of the week stories are read: handy for choosing when to publish or send the newsletter.
 */
class ReadingDays extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Wanneer lezers lezen';

    protected ?string $description = 'Gelezen per dag van de week, laatste 90 dagen';

    protected ?string $maxHeight = '220px';

    protected function getData(): array
    {
        return [
            'datasets' => [[
                'label' => 'Gelezen',
                'data' => array_values(Statistics::weekdays()),
                'backgroundColor' => 'rgba(0, 137, 122, 0.9)',
                'borderWidth' => 0,
                'borderRadius' => 4,
                'maxBarThickness' => 24,
            ]],
            'labels' => ['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]], 'x' => ['grid' => ['display' => false]]],
        ];
    }
}
