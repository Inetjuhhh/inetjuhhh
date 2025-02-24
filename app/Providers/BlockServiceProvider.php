<?php

namespace App\Providers;

use App\Blocks\GalleryBlock;
use FilamentTiptapEditor\TiptapEditor;
use Illuminate\Support\ServiceProvider;

class BlockServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        TiptapEditor::configureUsing(function (TiptapEditor $component) {
            $component
                ->blocks([
                    GalleryBlock::class
                ]);
            });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
