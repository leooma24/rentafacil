<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use  App\Filament\Widgets\StatsOverview;

class AdminDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-s-table-cells';

    protected static string $view = 'filament.pages.admin-dashboard';

    protected static ?string $navigationGroup = 'Administrador';

    protected static ?string $slug = 'escritorio';
    protected static ?string $title = 'Escritorio';

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverview::make(),
        ];
    }

    public static function canAccess() : bool
    {
        return auth()->user()->hasRole('super_admin');
    }


}
