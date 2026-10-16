<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\BlogResource;
use App\Filament\Resources\ResponseResource;
use App\Filament\Resources\SubscriberResource;
use App\Models\Blog;
use App\Models\BlogLike;
use App\Models\BlogView;
use App\Models\Response;
use App\Models\Subscriber;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The numbers at the top of the dashboard: how much the blog is read, and what needs attention.
 */
class BlogStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int | array | null $columns = ['md' => 3, 'xl' => 5];

    protected function getStats(): array
    {
        return [
            $this->views(),
            $this->blogs(),
            $this->waiting(),
            $this->subscribers(),
            $this->likes(),
        ];
    }

    // Read in the last 30 days, with a line per day and the change against the 30 days before
    private function views(): Stat
    {
        $perDay = BlogView::where('date', '>=', today()->subDays(29))
            ->selectRaw('date, SUM(views) as total')->groupBy('date')->pluck('total', 'date')
            ->mapWithKeys(fn ($total, $date) => [substr((string) $date, 0, 10) => (int) $total]);
        $line = collect(range(29, 0))->map(fn ($daysAgo) => $perDay[today()->subDays($daysAgo)->toDateString()] ?? 0);

        $now = $line->sum();
        $before = (int) BlogView::whereBetween('date', [today()->subDays(59), today()->subDays(30)])->sum('views');
        $change = $before > 0 ? round(($now - $before) / $before * 100) : null;

        return Stat::make('Gelezen · 30 dagen', number_format($now, 0, ',', '.'))
            ->description(match (true) {
                $change === null => 'Elke keer dat een lezer een verhaal opent',
                $change >= 0 => "{$change}% meer dan de 30 dagen ervoor",
                default => abs($change) . '% minder dan de 30 dagen ervoor',
            })
            ->descriptionIcon($change !== null && $change < 0 ? Heroicon::ArrowTrendingDown : Heroicon::ArrowTrendingUp)
            ->color($change !== null && $change < 0 ? 'gray' : 'success')
            ->chart($line->all());
    }

    private function blogs(): Stat
    {
        $drafts = Blog::where('status', 'draft')->count();
        $planned = Blog::where('status', 'published')->where('published_at', '>', now())->count();

        return Stat::make('Verhalen online', Blog::published()->count())
            ->description("{$drafts} concepten" . ($planned ? " · {$planned} ingepland" : ''))
            ->descriptionIcon(Heroicon::PencilSquare)
            ->url(BlogResource::getUrl('index'));
    }

    // Comments to review: orange while there are any, so it catches the eye
    private function waiting(): Stat
    {
        $waiting = Response::pending()->where('is_author', false)->count();

        return Stat::make('Reacties te beoordelen', $waiting)
            ->description($waiting ? 'Klik om ze goed te keuren' : 'Alles is bijgewerkt')
            ->descriptionIcon($waiting ? Heroicon::ChatBubbleLeftEllipsis : Heroicon::CheckCircle)
            ->color($waiting ? 'warning' : 'success')
            ->url(ResponseResource::getUrl('index'));
    }

    private function subscribers(): Stat
    {
        $new = Subscriber::confirmed()->where('confirmed_at', '>=', now()->startOfMonth())->count();

        return Stat::make('Abonnees', Subscriber::confirmed()->count())
            ->description($new ? "+{$new} deze maand" : 'Krijgen een mail bij elk nieuw verhaal')
            ->descriptionIcon(Heroicon::Envelope)
            ->url(SubscriberResource::getUrl('index'));
    }

    private function likes(): Stat
    {
        $recent = BlogLike::where('created_at', '>=', now()->subDays(30))->count();

        return Stat::make('Hartjes', BlogLike::count())
            ->description($recent ? "+{$recent} in de laatste 30 dagen" : 'Lezers geven een hartje onder een verhaal')
            ->descriptionIcon(Heroicon::Heart)
            // Red only when there are new hearts: an explanation shouldn't look like a warning
            ->color($recent ? 'danger' : 'gray');
    }
}
