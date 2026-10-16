<?php

namespace App\Providers;

use App\Models\Blog;
use App\Models\Country;
use App\Models\Trip;
use App\Support\ImageOptimizer;
use App\Support\SiteCache;
use Awcodes\Curator\Facades\Curator;
use Awcodes\Curator\Models\Media;
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
            $view->with('countries', SiteCache::menuCountries());
        });

        // Trips in the menu, journeys and city trips apart: only those with a story visitors can read
        View::composer('layouts.navigation', function ($view) {
            $trips = SiteCache::menuTrips();

            $view->with('navTrips', $trips->where('kind', '!=', Trip::KIND_CITY)->values())
                ->with('navCityTrips', $trips->where('kind', Trip::KIND_CITY)->values());
        });

        // The remembered menu and map are out of date as soon as a blog, trip or country changes
        foreach ([Blog::class, Trip::class, Country::class] as $model) {
            $model::saved(fn () => SiteCache::forget());
            $model::deleted(fn () => SiteCache::forget());
        }

        // Phone photos are resized in the browser before uploading (max 2400px, never enlarged),
        // which keeps uploads fast on holiday wifi and the storage small.
        Curator::imageResizeMode('contain')
            ->imageResizeTargetWidth('2400')
            ->imageResizeTargetHeight('2400')
            ->maxSize(30 * 1024)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

        // ...and shrunk to a file size budget on the server when they are stored (see ImageOptimizer)
        config('curator.model')::saving(function (Media $media) {
            if ($media->isDirty('path')) {
                ImageOptimizer::optimizeMedia($media);
            }
        });

        // Hearts: enough for a real reader clicking around, too few to push a counter up
        RateLimiter::for('likes', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        FileUpload::configureUsing(fn (FileUpload $upload) => $upload->automaticallyUpscaleImagesWhenResizing(false));
    }
}
