<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Concerns\HasPeriod;
use App\Models\Blog;
use App\Models\Trip;
use App\Support\Statistics as Numbers;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

/**
 * The statistics per blog: a list to search and sort, and per blog how often it was read (per day
 * after publishing), its likes and comments, where its readers live and how they came to it.
 * The dashboard has the overview of all blogs together.
 */
class Statistics extends Page
{
    use HasPeriod;

    public const SORTS = ['reads' => 'Gelezen', 'likes' => 'Likes', 'rate' => 'Per 100', 'comments' => 'Reacties'];

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string | \UnitEnum | null $navigationGroup = 'Lezers';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Statistieken';

    protected static ?string $slug = 'statistieken';

    protected string $view = 'filament.pages.statistics';

    #[Url]
    public ?int $blog = null;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: null)]
    public ?int $trip = null;

    #[Url(except: 'reads')]
    public string $sort = 'reads';

    // Blogs shown in the list; "Toon meer" adds the next ones
    public const PER_PAGE = 25;

    public int $shown = self::PER_PAGE;

    public function updatedSearch(): void
    {
        $this->shown = self::PER_PAGE;
    }

    public function updatedTrip(): void
    {
        $this->shown = self::PER_PAGE;
    }

    public function showMore(): void
    {
        $this->shown += self::PER_PAGE;
    }

    public function mount(): void
    {
        // The details of a blog: all its reads since it was published
        $this->period = 'all';
    }

    public function getTitle(): string | Htmlable
    {
        return $this->record()?->title ?? 'Statistieken per blog';
    }

    public function updatedSort(): void
    {
        if (! array_key_exists($this->sort, self::SORTS)) {
            $this->sort = 'reads';
        }
    }

    public function record(): ?Blog
    {
        return $this->blog ? Blog::published()->with('trip')->find($this->blog) : null;
    }

    protected function getViewData(): array
    {
        $record = $this->record();

        if (! $record) {
            return [
                'record' => null,
                'blogs' => Numbers::blogs('all', $this->sort, search: trim($this->search), tripId: $this->trip),
                'trips' => Trip::whereHas('blogs')->orderByDesc('starts_on')->pluck('name', 'id'),
            ];
        }

        $all = Numbers::blogs('all');
        $numbers = $all->firstWhere('id', $record->id);

        return [
            'record' => $record,
            'numbers' => $numbers,
            'rank' => $all->search(fn ($row) => $row->id === $record->id) + 1,
            'total' => $all->count(),
            'perDay' => Numbers::blogPerDay($record),
            'countries' => Numbers::countries($this->period, $record->id),
            'sources' => Numbers::sources($this->period, $record->id),
        ];
    }
}
