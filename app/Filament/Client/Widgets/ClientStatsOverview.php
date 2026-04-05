<?php

namespace App\Filament\Client\Widgets;

use App\Models\Rental;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClientStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();

        // Find rentals associated with this user through companies
        $companyIds = $user->companies()->pluck('companies.id');
        $activeRentals = Rental::whereIn('company_id', $companyIds)
            ->where('status', 'activa')
            ->count();
        $overdueRentals = Rental::whereIn('company_id', $companyIds)
            ->where('status', 'vencida')
            ->count();
        $totalPayments = Payment::whereIn('company_id', $companyIds)
            ->where('status', 'completado')
            ->sum('amount');

        return [
            Stat::make('Rentas Activas', $activeRentals)
                ->icon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make('Rentas Vencidas', $overdueRentals)
                ->icon('heroicon-o-exclamation-triangle')
                ->color($overdueRentals > 0 ? 'danger' : 'success'),
            Stat::make('Total Pagado', '$' . number_format($totalPayments, 2))
                ->icon('heroicon-o-banknotes')
                ->color('info'),
        ];
    }
}
