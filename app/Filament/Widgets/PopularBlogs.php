<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasPeriod;
use App\Support\Statistics;
use Filament\Widgets\Widget;

/**
 * The most popular stories: most read, most likes, or most likes per 100 readers (the stories that
 * really touch people, also when fewer read them), over 30 days, a year or all time.
 */
class PopularBlogs extends Widget
{
    use HasPeriod;

    public const ORDERS = ['reads' => 'Meest gelezen', 'likes' => 'Meeste likes', 'rate' => 'Likes per 100'];

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.popular-blogs';

    public string $by = 'reads';

    public function updatedBy(): void
    {
        if (! array_key_exists($this->by, self::ORDERS)) {
            $this->by = 'reads';
        }
    }

    protected function getViewData(): array
    {
        return ['blogs' => Statistics::blogs($this->period, $this->by, limit: 5)
            ->filter(fn ($blog) => $this->by !== 'rate' || $blog->rate !== null)
            ->values()];
    }
}
