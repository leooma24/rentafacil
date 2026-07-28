<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PaymentStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        $now = Carbon::now();

        $monthRevenue = Payment::where('company_id', $tenant->id)
            ->where('status', 'completado')
            ->whereYear('payment_date', $now->year)
            ->whereMonth('payment_date', $now->month)
            ->sum('amount');

        $lastMonthRevenue = Payment::where('company_id', $tenant->id)
            ->where('status', 'completado')
            ->whereYear('payment_date', $now->copy()->subMonth()->year)
            ->whereMonth('payment_date', $now->copy()->subMonth()->month)
            ->sum('amount');

        $pendingPayments = Payment::where('company_id', $tenant->id)
            ->where('status', 'pendiente')
            ->count();

        $activeRentals = $tenant->rentals()->where('status', 'activa')->count();
        $overdueRentals = $tenant->rentals()->where('status', 'vencida')->count();

        $totalOwed = app(\App\Support\AccountStatement::class)->totalForCompany($tenant);

        $trend = $lastMonthRevenue > 0
            ? round((($monthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
            : 0;

        return [
            Stat::make('Ingresos del Mes', '$' . number_format($monthRevenue, 2))
                ->description($trend >= 0 ? "+{$trend}% vs mes anterior" : "{$trend}% vs mes anterior")
                ->descriptionIcon($trend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($trend >= 0 ? 'success' : 'danger'),
            Stat::make('Pagos Pendientes', $pendingPayments)
                ->description('Por cobrar')
                ->color($pendingPayments > 0 ? 'warning' : 'success'),
            Stat::make('Total por Cobrar', '$' . number_format($totalOwed, 2))
                ->description('Suma de lo que deben tus clientes')
                ->color($totalOwed > 0 ? 'danger' : 'success'),
            Stat::make('Rentas Activas', $activeRentals)
                ->description('En curso')
                ->color('info'),
            Stat::make('Rentas Vencidas', $overdueRentals)
                ->description('Requieren atención')
                ->color($overdueRentals > 0 ? 'danger' : 'success'),
        ];
    }
}
