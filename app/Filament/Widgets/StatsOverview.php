<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\WashingMachine;
use Filament\Facades\Filament;

class StatsOverview extends BaseWidget
{
    // Sin esto el widget se queda en el placeholder de carga y nunca aparece.
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        $washingMachines = $tenant->washingMachines()->count();
        $washingMachinesRented = $tenant->washingMachines()->where('status', 'rentada')->count();
        $washingMachinesAvailable = $tenant->washingMachines()->where('status', 'disponible')->count();
        $washingMachinesMaintenance = $tenant->washingMachines()->where('status', 'mantenimiento')->count();

        return [
            //
            Stat::make('Lavadoras', $washingMachines),
            Stat::make('Rentadas', $washingMachinesRented),
            Stat::make('Disponibles', $washingMachinesAvailable),
            Stat::make('En Mantenimiento', $washingMachinesMaintenance),
        ];
    }
}
