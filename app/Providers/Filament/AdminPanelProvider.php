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
            ->brandName('Renta Fácil')
            ->font('Inter')
            ->colors([
                'danger' => Color::Rose,
                'gray' => Color::Gray,
                'info' => Color::Blue,
                // El mismo cyan del landing, para que sitio y panel se sientan
                // la misma marca en vez de dos productos parecidos.
                'primary' => Color::hex('#06b6d4'),
                'success' => Color::Emerald,
                'warning' => Color::Orange,
            ])
            ->sidebarCollapsibleOnDesktop()
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
                'Mi cuenta' => NavigationGroup::make('Mi cuenta', 'heroicon-o-user-circle'),
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
            // Filament arranca el menú con isOpen persistido en true. En
            // escritorio da igual porque la barra va fija, pero en celular eso
            // es una cortina encima del contenido al entrar.
            //
            // Se fija según el tamaño de pantalla, no solo se cierra: como el
            // valor se persiste, cerrarlo en el celular dejaba el menú encogido
            // a puros iconos la próxima vez que se abriera en computadora.
            ->renderHook('panels::body.end', fn () => new HtmlString(
                '<script>document.addEventListener("alpine:initialized",function(){'
                . 'if(!window.Alpine||!Alpine.store("sidebar"))return;'
                . 'if(window.innerWidth<1024){Alpine.store("sidebar").close()}'
                . 'else{Alpine.store("sidebar").open()}'
                . '})</script>'
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
