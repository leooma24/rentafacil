<?php

namespace App\Filament\Resources\RentalResource\Widgets;

use App\Filament\Resources\Widgets\CatalogoStats;
use App\Support\AccountStatement;
use App\Support\Statement;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RentasStats extends CatalogoStats
{
    protected function getStats(): array
    {
        $tenant = $this->tenant();

        $activas = $tenant->rentals()->where('status', 'activa')->count();
        $vencidas = $tenant->rentals()->where('status', 'vencida')->count();

        // Vencen en los próximos siete días: es la cobranza de la semana que viene.
        $porVencer = $tenant->rentals()
            ->where('status', 'activa')
            ->whereDate('end_date', '>=', today())
            ->whereDate('end_date', '<=', today()->addDays(7))
            ->count();

        $adeudo = (float) app(AccountStatement::class)
            ->forCompany($tenant)
            ->sum(fn (Statement $estado) => $estado->total);

        return [
            Stat::make('Activas', $activas)
                ->description('rentas al corriente')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Vencidas', $vencidas)
                ->description($vencidas > 0 ? $this->dinero($adeudo) . ' por cobrar' : 'ninguna atrasada')
                ->descriptionIcon($vencidas > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($vencidas > 0 ? 'danger' : 'success'),

            Stat::make('Vencen esta semana', $porVencer)
                ->description('en los próximos 7 días')
                ->descriptionIcon('heroicon-m-clock')
                ->color($porVencer > 0 ? 'warning' : 'success'),
        ];
    }
}
