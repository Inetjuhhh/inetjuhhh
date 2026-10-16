<?php

namespace App\Filament\Widgets;

use App\Models\Blog;
use Filament\Widgets\ChartWidget;

/**
 * How many stories went online per year, since the very first one.
 */
class BlogsPerYear extends ChartWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Verhalen per jaar';

    protected ?string $maxHeight = '220px';

    protected function getData(): array
    {
        $perYear = Blog::published()->get(['published_at', 'created_at'])
            ->countBy(fn (Blog $blog) => $blog->publishedDate()->year);

        $years = $perYear->isEmpty() ? collect([now()->year]) : collect(range($perYear->keys()->min(), now()->year));

        return [
            'datasets' => [[
                'label' => 'Verhalen',
                'data' => $years->map(fn ($year) => $perYear[$year] ?? 0)->all(),
            ]],
            'labels' => $years->all(),
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
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
