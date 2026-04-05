<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class MonthlyRevenueChart extends ChartWidget
{
    protected static ?string $heading = 'Ingresos Mensuales';
    protected static ?int $sort = 2;
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $tenant = Filament::getTenant();

        $months = collect(range(5, 0))->map(function ($i) {
            return Carbon::now()->subMonths($i);
        });

        $revenues = $months->map(function ($month) use ($tenant) {
            return Payment::where('company_id', $tenant->id)
                ->where('status', 'completado')
                ->whereYear('payment_date', $month->year)
                ->whereMonth('payment_date', $month->month)
                ->sum('amount');
        });

        return [
            'datasets' => [
                [
                    'label' => 'Ingresos ($)',
                    'data' => $revenues->values()->toArray(),
                    'backgroundColor' => 'rgba(6, 182, 212, 0.2)',
                    'borderColor' => 'rgb(6, 182, 212)',
                    'fill' => true,
                ],
            ],
            'labels' => $months->map(fn ($m) => $m->translatedFormat('M Y'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
