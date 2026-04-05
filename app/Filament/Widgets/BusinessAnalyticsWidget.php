<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Models\Rental;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BusinessAnalyticsWidget extends BaseWidget
{
    protected static ?int $sort = 10;
    protected static ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        $now = Carbon::now();

        // Occupancy rate
        $totalMachines = $tenant->washingMachines()->count();
        $rentedMachines = $tenant->washingMachines()->where('status', 'rentada')->count();
        $occupancyRate = $totalMachines > 0 ? round(($rentedMachines / $totalMachines) * 100, 1) : 0;

        // Average days overdue
        $overdueRentals = $tenant->rentals()->where('status', 'vencida')->get();
        $avgDaysOverdue = $overdueRentals->count() > 0
            ? round($overdueRentals->avg(fn ($r) => now()->diffInDays($r->end_date)), 1)
            : 0;

        // Churn rate (customers who didn't renew in last 30 days)
        $completedLast30 = $tenant->rentals()
            ->where('status', 'completada')
            ->where('updated_at', '>=', $now->copy()->subDays(30))
            ->distinct('customer_id')
            ->count('customer_id');
        $activeLast30 = $tenant->rentals()
            ->whereIn('status', ['activa', 'vencida'])
            ->distinct('customer_id')
            ->count('customer_id');
        $totalCustomersLast30 = $completedLast30 + $activeLast30;
        $churnRate = $totalCustomersLast30 > 0 ? round(($completedLast30 / $totalCustomersLast30) * 100, 1) : 0;

        // Revenue projection (next 3 months based on active rentals)
        $activeRentals = $tenant->rentals()->where('status', 'activa')->count();
        $avgPayment = Payment::where('company_id', $tenant->id)
            ->where('status', 'completado')
            ->where('payment_date', '>=', $now->copy()->subDays(90))
            ->avg('amount') ?? 0;
        $settings = $tenant->settings;
        $paymentsPerMonth = $settings && $settings->days_per_payment > 0
            ? 30 / $settings->days_per_payment
            : 4;
        $projection3m = $activeRentals * $avgPayment * $paymentsPerMonth * 3;

        // Customer lifetime value
        $totalRevenue = Payment::where('company_id', $tenant->id)->where('status', 'completado')->sum('amount');
        $totalCustomers = $tenant->customers()->count();
        $ltv = $totalCustomers > 0 ? round($totalRevenue / $totalCustomers, 2) : 0;

        return [
            Stat::make('Tasa de Ocupación', $occupancyRate . '%')
                ->description("{$rentedMachines}/{$totalMachines} lavadoras rentadas")
                ->color($occupancyRate >= 70 ? 'success' : ($occupancyRate >= 40 ? 'warning' : 'danger'))
                ->chart($this->getOccupancyTrend($tenant)),

            Stat::make('Días Prom. de Atraso', $avgDaysOverdue)
                ->description($overdueRentals->count() . ' rentas vencidas')
                ->color($avgDaysOverdue <= 3 ? 'success' : ($avgDaysOverdue <= 7 ? 'warning' : 'danger')),

            Stat::make('Tasa de Abandono', $churnRate . '%')
                ->description('Últimos 30 días')
                ->color($churnRate <= 10 ? 'success' : ($churnRate <= 25 ? 'warning' : 'danger')),

            Stat::make('Proyección 3 Meses', '$' . number_format($projection3m, 0))
                ->description('Basado en rentas activas')
                ->color('info'),

            Stat::make('Valor por Cliente', '$' . number_format($ltv, 2))
                ->description('Lifetime value promedio')
                ->color('success'),
        ];
    }

    protected function getOccupancyTrend($tenant): array
    {
        return collect(range(6, 0))->map(function ($i) use ($tenant) {
            $date = now()->subDays($i * 5);
            $total = $tenant->washingMachines()->where('created_at', '<=', $date)->count();
            $rented = $tenant->rentals()
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->whereIn('status', ['activa', 'vencida'])
                ->count();
            return $total > 0 ? round(($rented / $total) * 100) : 0;
        })->toArray();
    }

    protected function getColumns(): int
    {
        return 5;
    }
}
