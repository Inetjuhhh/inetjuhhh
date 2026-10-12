<?php

namespace App\Providers;

use App\Models\Country;
use App\Models\Trip;
use Awcodes\Curator\Facades\Curator;
use Filament\Forms\Components\FileUpload;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Countries in the menu and footer: only those with at least one story visitors can read
        View::composer(['layouts.navigation', 'layouts.app'], function ($view) {
            $view->with('countries', Country::whereHas('blogs', fn ($query) => $query->published())
                ->orderBy('name', 'asc')
                ->get());
        });

        // Trips in the menu: only those with at least one story visitors can read
        View::composer('layouts.navigation', function ($view) {
            $view->with('navTrips', Trip::whereHas('blogs', fn ($query) => $query->published())
                ->orderByDesc('starts_on')
                ->get());
        });

        // Phone photos are resized in the browser before uploading (max 2400px, never enlarged),
        // which keeps uploads fast on holiday wifi and the storage small.
        Curator::imageResizeMode('contain')
            ->imageResizeTargetWidth('2400')
            ->imageResizeTargetHeight('2400')
            ->maxSize(30 * 1024)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

        // Hearts: enough for a real reader clicking around, too few to push a counter up
        RateLimiter::for('likes', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        FileUpload::configureUsing(fn (FileUpload $upload) => $upload->automaticallyUpscaleImagesWhenResizing(false));
    }
}
