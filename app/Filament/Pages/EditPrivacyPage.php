<?php

namespace App\Filament\Pages;

use App\Models\Page as SitePage;
use Filament\Support\Icons\Heroicon;

/**
 * The privacy page: what the site keeps of its readers, and who helps (Brevo, Cloudflare, …). Change it when the site changes.
 */
class EditPrivacyPage extends EditSitePage
{
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string | \UnitEnum | null $navigationGroup = 'Schrijven';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Privacy';

    protected static ?string $title = 'Privacy';

    protected static ?string $slug = 'privacy';


    protected static function sitePage(): SitePage
    {
        return SitePage::privacy();
    }

    protected static function siteRoute(): string
    {
        return 'privacy';
    }
}
