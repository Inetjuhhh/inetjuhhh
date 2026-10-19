<?php

namespace App\Providers;

use App\Console\Commands\ApplySubjects;
use App\Console\Commands\ImportWordPress;
use App\Models\Blog;
use App\Models\Country;
use App\Models\Trip;
use App\Livewire\CuratorPanel;
use App\Support\ImageOptimizer;
use App\Support\PhotoFingerprint;
use App\Support\SiteCache;
use Awcodes\Curator\Facades\Curator;
use Awcodes\Curator\Models\Media;
use Filament\Forms\Components\FileUpload;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Console\Seeds\SeedCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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
        // On the live server the real blogs are in the database: commands that empty it, or overwrite
        // it with example or WordPress content, refuse to run there (also with --force)
        $live = $this->app->isProduction();
        DB::prohibitDestructiveCommands($live);
        SeedCommand::prohibit($live);
        ImportWordPress::prohibit($live);
        ApplySubjects::prohibit($live);

        // Countries in the footer: only those with at least one story visitors can read
        View::composer('layouts.app', function ($view) {
            $view->with('countries', SiteCache::menuCountries());
        });

        // The remembered menu and map are out of date as soon as a blog, trip or country changes
        foreach ([Blog::class, Trip::class, Country::class] as $model) {
            $model::saved(fn () => SiteCache::forget());
            $model::deleted(fn () => SiteCache::forget());
        }

        // Phone photos are resized in the browser before uploading (max 2400px, never enlarged),
        // which keeps uploads fast on holiday wifi and the storage small.
        // Stored on the public disk, like all photos: since Curator 5.6 the default follows FILESYSTEM_DISK,
        // which is the private "local" disk, so new photos showed up as an empty placeholder
        Curator::disk('public')
            ->visibility('public')
            ->imageResizeMode('contain')
            ->imageResizeTargetWidth('2400')
            ->imageResizeTargetHeight('2400')
            ->maxSize(30 * 1024)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

        // Curator's media window, with a question when an upload is already in the library (replaces Curator's own)
        Livewire::component('curator-panel', CuratorPanel::class);

        // ...and shrunk to a file size budget on the server when they are stored (see ImageOptimizer)
        config('curator.model')::saving(function (Media $media) {
            if ($media->isDirty('path')) {
                ImageOptimizer::optimizeMedia($media);
                // To notice the same photo when it is uploaded again (see CuratorPanel)
                $media->fingerprint = PhotoFingerprint::forMedia($media);
            }
        });

        // Hearts: enough for a real reader clicking around, too few to push a counter up
        RateLimiter::for('likes', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        FileUpload::configureUsing(fn (FileUpload $upload) => $upload->automaticallyUpscaleImagesWhenResizing(false));
    }
}
