<?php

namespace App\Filament\Widgets;

use App\Models\BlogView;
use Filament\Widgets\ChartWidget;

/**
 * How often the blog was read per day, over a week, a month or a quarter.
 */
class ViewsChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Gelezen per dag';

    protected ?string $description = 'Hoe vaak lezers een verhaal openden (zoekmachines en jijzelf tellen niet mee)';

    protected ?string $maxHeight = '260px';

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return ['7' => '7 dagen', '30' => '30 dagen', '90' => '90 dagen'];
    }

    protected function getData(): array
    {
        $days = (int) $this->filter;

        $perDay = BlogView::where('date', '>=', today()->subDays($days - 1))
            ->selectRaw('date, SUM(views) as total')->groupBy('date')->pluck('total', 'date')
            ->mapWithKeys(fn ($total, $date) => [substr((string) $date, 0, 10) => (int) $total]);

        // Every day, also the quiet ones without any reader
        $dates = collect(range($days - 1, 0))->map(fn ($daysAgo) => today()->subDays($daysAgo));

        return [
            'datasets' => [[
                'label' => 'Gelezen',
                'data' => $dates->map(fn ($date) => $perDay[$date->toDateString()] ?? 0)->all(),
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => $dates->map(fn ($date) => $date->translatedFormat($days > 30 ? 'j M' : 'D j'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    // The axis starts at 0, so a quiet day doesn't look like a crash; the heading says what the line is
    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
