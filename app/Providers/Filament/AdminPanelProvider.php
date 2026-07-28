<?php

namespace App\Providers\Filament;

use App\Models\Company;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\HtmlString;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Filament\Pages\Tenancy\RegisterCompany;
use App\Filament\Pages\Tenancy\EditCompanyProfile;
use Filament\Navigation\NavigationGroup;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('propietario')
            ->path('propietario')
            ->login()
            ->registration()
            ->registrationRouteSlug('registrar')
            ->passwordReset()
            ->brandLogo(asset('img/logo.png'))
            ->brandLogoHeight('4rem')
            ->favicon(asset('img/favicon.ico'))
            ->brandName('Renta Facíl')
            ->font('Roboto')
            ->colors([
                'danger' => Color::Rose,
                'gray' => Color::Gray,
                'info' => Color::Blue,
                'primary' => Color::Cyan,
                'success' => Color::Emerald,
                'warning' => Color::Orange,
            ])
            ->profile()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->navigationGroups([
                'Gestión Principal' => NavigationGroup::make('Gestión Principal', 'heroicon-s-home'),
                'Finanzas' => NavigationGroup::make('Finanzas', 'heroicon-o-currency-dollar'),
                'Servicios' => NavigationGroup::make('Servicios', 'heroicon-o-cog'),
                'Configuración' => NavigationGroup::make('Configuración', 'heroicon-o-cog'),
                'Administrador' => NavigationGroup::make('Administrador', 'heroicon-o-cog'),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->plugins([
                \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make(),
                \Saade\FilamentFullCalendar\FilamentFullCalendarPlugin::make(),
            ])
            ->renderHook('panels::head.end', fn () => new HtmlString(
                '<link rel="manifest" href="/manifest.json">' .
                '<meta name="theme-color" content="#06b6d4">' .
                '<script>if("serviceWorker" in navigator){navigator.serviceWorker.register("/sw.js")}</script>'
            ))
            ->renderHook('panels::body.start', function () {
                if (auth()->user()?->hasRole('super_admin')) {
                    return '';
                }

                return new HtmlString(
                    \App\Support\PanelBanner::for(\Filament\Facades\Filament::getTenant())
                );
            })
            ->tenant(Company::class)
            ->tenantRegistration(RegisterCompany::class)
            ->tenantProfile(EditCompanyProfile::class);
    }
}
