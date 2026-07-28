<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\RentalResource;
use App\Support\AccountStatement;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Lo primero que ve el dueño: qué tiene que hacer hoy.
 */
class TodayStats extends BaseWidget
{
    protected static ?int $sort = 0;

    // Sin esto el widget se queda en el placeholder de carga y nunca aparece.
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();

        $totalOwed = app(AccountStatement::class)->totalForCompany($tenant);

        $overdue = $tenant->rentals()->where('status', 'vencida')->count();

        $dueSoon = $tenant->rentals()
            ->where('status', 'activa')
            ->whereBetween('end_date', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
            ->count();

        $rentalsUrl = RentalResource::getUrl('index');

        return [
            Stat::make('Por cobrar', '$' . number_format($totalOwed, 2))
                ->description('Lo que te deben tus clientes')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($totalOwed > 0 ? 'danger' : 'success')
                ->url($rentalsUrl),

            Stat::make('Vencidas', $overdue)
                ->description($overdue === 1 ? 'renta por cobrar ya' : 'rentas por cobrar ya')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdue > 0 ? 'danger' : 'success')
                ->url($rentalsUrl),

            Stat::make('Vencen esta semana', $dueSoon)
                ->description('en los próximos 7 días')
                ->descriptionIcon('heroicon-m-clock')
                ->color($dueSoon > 0 ? 'warning' : 'success')
                ->url($rentalsUrl),
        ];
    }
}
