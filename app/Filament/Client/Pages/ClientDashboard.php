<?php

namespace App\Filament\Client\Pages;

use Filament\Pages\Dashboard;

class ClientDashboard extends Dashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $title = 'Mi Panel';

    public function getWidgets(): array
    {
        return [
            \App\Filament\Client\Widgets\ClientStatsOverview::class,
            \App\Filament\Client\Widgets\MyRentals::class,
            \App\Filament\Client\Widgets\MyPayments::class,
        ];
    }
}
