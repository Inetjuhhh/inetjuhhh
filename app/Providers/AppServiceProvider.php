<?php

namespace App\Providers;

use App\Models\Country;
use Awcodes\Curator\Facades\Curator;
use Filament\Forms\Components\FileUpload;
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
        View::share('countries', Country::orderBy('name', 'asc')->get());

        // Phone photos are resized in the browser before uploading (max 2400px, never enlarged),
        // which keeps uploads fast on holiday wifi and the storage small.
        Curator::imageResizeMode('contain')
            ->imageResizeTargetWidth('2400')
            ->imageResizeTargetHeight('2400')
            ->maxSize(30 * 1024)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

        FileUpload::configureUsing(fn (FileUpload $upload) => $upload->automaticallyUpscaleImagesWhenResizing(false));
    }
}
