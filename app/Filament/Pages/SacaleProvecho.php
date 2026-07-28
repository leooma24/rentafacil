<?php

namespace App\Filament\Pages;

use App\Support\Provecho;
use Filament\Facades\Filament;
use Filament\Pages\Page;

/**
 * La guía de lo que la app puede hacer por el dueño y todavía no le hace.
 *
 * No es un manual: cada herramienta se cuenta desde lo que gana con ella, y
 * cuando los datos lo permiten se le dice qué está dejando sobre la mesa
 * ahorita mismo, con sus propios números.
 */
class SacaleProvecho extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';

    protected static ?string $navigationLabel = 'Sácale provecho';

    protected static ?string $title = 'Sácale provecho a tu sistema';

    protected static ?string $navigationGroup = 'Mi cuenta';

    protected static ?int $navigationSort = -1;

    protected static ?string $slug = 'sacale-provecho';

    /** La guía habla de reportes y precios: es para quien manda. */
    public static function canAccess(): bool
    {
        return \App\Support\Acceso::soloDueno();
    }

    protected static string $view = 'filament.pages.sacale-provecho';

    /** El número de herramientas sin estrenar, para que se vea desde el menú. */
    public static function getNavigationBadge(): ?string
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return null;
        }

        $pendientes = Provecho::for($tenant)->totalSinEstrenar();

        return $pendientes > 0 ? (string) $pendientes : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getViewData(): array
    {
        return ['provecho' => Provecho::for(Filament::getTenant())];
    }
}
