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

    // Blogs shown; "Toon meer" adds the next ones
    public const PER_PAGE = 5;

    public int $shown = self::PER_PAGE;

    public function showMore(): void
    {
        $this->shown += 10;
    }

    // Another order or period starts at the top five again
    public function updated(string $property): void
    {
        if (in_array($property, ['by', 'period'], true)) {
            $this->shown = self::PER_PAGE;
        }
    }

    public function updatedBy(): void
    {
        if (! array_key_exists($this->by, self::ORDERS)) {
            $this->by = 'reads';
        }
    }

    protected function getViewData(): array
    {
        // Only blogs with something to rank on: read, liked, or enough readers for a rate
        $blogs = Statistics::blogs($this->period, $this->by)
            ->filter(fn ($blog) => match ($this->by) {
                'rate' => $blog->rate !== null,
                'likes' => $blog->likes > 0,
                default => $blog->reads > 0,
            })
            ->values();

        return ['blogs' => $blogs->take($this->shown), 'total' => $blogs->count()];
    }
}
