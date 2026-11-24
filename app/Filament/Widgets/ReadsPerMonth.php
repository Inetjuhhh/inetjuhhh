<?php

namespace App\Filament\Widgets;

use App\Models\BlogView;
use App\Support\ViewCounter;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * How often the blog was read per month, over the last 12 months. Months before the new counting
 * are lighter: those numbers are higher than the real readers (see ViewCounter).
 */
class ReadsPerMonth extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Gelezen per maand';

    protected ?string $maxHeight = '220px';

    public function getDescription(): ?string
    {
        return ViewCounter::countingSince()
            ? 'Laatste 12 maanden. De lichte maanden zijn van vóór de nieuwe telling.'
            : 'Laatste 12 maanden';
    }

    protected function getData(): array
    {
        $months = collect(range(11, 0))->map(fn ($ago) => today()->startOfMonth()->subMonthsNoOverflow($ago));

        $perMonth = BlogView::where('date', '>=', $months->first())->get(['date', 'views'])
            ->groupBy(fn (BlogView $row) => Carbon::parse($row->date)->format('Y-m'))
            ->map(fn ($rows) => (int) $rows->sum('views'));

        $since = ViewCounter::countingSince();

        return [
            'datasets' => [[
                'label' => 'Gelezen',
                'data' => $months->map(fn ($month) => $perMonth[$month->format('Y-m')] ?? 0)->all(),
                'backgroundColor' => $months->map(fn ($month) => ($since === null || $month->copy()->endOfMonth()->toDateString() >= $since)
                    ? 'rgba(0, 137, 122, 0.9)' : 'rgba(0, 137, 122, 0.3)')->all(),
                'borderRadius' => 4,
                'maxBarThickness' => 24,
            ]],
            'labels' => $months->map(fn ($month) => $month->translatedFormat('M'))->all(),
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
