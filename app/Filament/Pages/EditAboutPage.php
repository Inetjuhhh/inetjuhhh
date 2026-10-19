<?php

namespace App\Filament\Pages;

use App\Models\Page as SitePage;
use Filament\Support\Icons\Heroicon;

/**
 * The "Ik & wij" page.
 */
class EditAboutPage extends EditSitePage
{
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Ik & wij';

    protected static ?string $title = 'Ik & wij';

    protected static ?string $slug = 'ik-en-wij';

    protected static ?int $navigationSort = 2;

    protected static function sitePage(): SitePage
    {
        return SitePage::about();
    }

    protected static function siteRoute(): string
    {
        return 'about';
    }
}
