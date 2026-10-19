<?php

namespace App\Providers\Filament;

use App\Filament\InitialsAvatar;
use App\Support\Avatar;
use Filament\FontProviders\LocalFontProvider;
use Filament\Pages\BasePage;
use Filament\Pages\Dashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Awcodes\Curator\CuratorPlugin;
use Filament\Support\Icons\Heroicon;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->profile(isSimple: false)
            ->favicon(fn () => Avatar::faviconUrl())
            ->brandName('Inetjuhhh')
            ->colors([
                'primary' => Color::hex('#3E8F86'),
                'gray' => Color::Stone,
            ])
            // Hosted here (public/fonts/figtree), not loaded from fonts.bunny.net on every page
            ->font('Figtree', url: asset('fonts/figtree/figtree.css'), provider: LocalFontProvider::class)
            // Initials made here, instead of a picture from ui-avatars.com on every page
            ->defaultAvatarProvider(InitialsAvatar::class)
            // On an iPad the menu can be folded away, so lists and the editor get the full width
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('16rem')
            ->unsavedChangesAlerts()
            ->databaseTransactions()
            ->plugins([
                CuratorPlugin::make()
                    ->label('Foto')
                    ->pluralLabel('Mediabibliotheek')
                    ->navigationIcon(Heroicon::OutlinedPhoto)
                    ->navigationGroup('Schrijven')
                    ->navigationSort(3),
            ])
            ->navigationGroups([
                NavigationGroup::make('Schrijven'),
                NavigationGroup::make('Lezers'),
                NavigationGroup::make('Beheer'),
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    public function boot(): void
    {
        BasePage::stickyFormActions();
    }
}
