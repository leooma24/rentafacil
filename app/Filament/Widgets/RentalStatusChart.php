<?php

namespace App\Filament\Widgets;

use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class RentalStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Estado de Rentas';
    protected static ?int $sort = 3;
    protected static ?string $maxHeight = '300px';
    // Sin esto el widget se queda en el placeholder de carga y nunca aparece.
    protected static bool $isLazy = false;

    protected function getData(): array
    {
        $tenant = Filament::getTenant();

        $activas = $tenant->rentals()->where('status', 'activa')->count();
        $vencidas = $tenant->rentals()->where('status', 'vencida')->count();
        $completadas = $tenant->rentals()->where('status', 'completada')->count();
        $canceladas = $tenant->rentals()->where('status', 'cancelada')->count();

        return [
            'datasets' => [
                [
                    'data' => [$activas, $vencidas, $completadas, $canceladas],
                    'backgroundColor' => [
                        'rgb(6, 182, 212)',
                        'rgb(239, 68, 68)',
                        'rgb(16, 185, 129)',
                        'rgb(156, 163, 175)',
                    ],
                ],
            ],
            'labels' => ['Activas', 'Vencidas', 'Completadas', 'Canceladas'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
