<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\BlogResource;
use App\Models\Blog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The five stories read most in the last 30 days.
 */
class PopularBlogs extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Meest gelezen · 30 dagen';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Blog::published()
                ->withSum(['views as recent_views' => fn ($q) => $q->where('date', '>=', today()->subDays(29))], 'views')
                ->withCount(['likes', 'comments'])
                ->whereHas('views', fn ($q) => $q->where('date', '>=', today()->subDays(29)))
                ->orderByDesc('recent_views')
                ->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('title')
                    ->label('Verhaal')
                    ->wrap()
                    ->url(fn (Blog $record) => BlogResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('recent_views')
                    ->label('Gelezen')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('likes_count')
                    ->label('♥')
                    ->alignEnd()
                    ->visibleFrom('sm'),
                TextColumn::make('comments_count')
                    ->label('Reacties')
                    ->alignEnd()
                    ->visibleFrom('sm'),
            ])
            ->emptyStateHeading('Nog niets gelezen')
            ->emptyStateDescription('Zodra lezers je verhalen openen, zie je hier welke het populairst zijn.')
            ->emptyStateIcon('heroicon-o-book-open');
    }
}
