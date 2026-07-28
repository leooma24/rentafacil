<?php

namespace App\Filament\Widgets;

use App\Models\Maintenance;
use App\Models\Rental;
use Filament\Facades\Filament;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class RentalCalendarWidget extends FullCalendarWidget
{
    protected static ?int $sort = 8;
    // Sin esto el widget se queda en el placeholder de carga y nunca aparece.
    protected static bool $isLazy = false;

    public function fetchEvents(array $fetchInfo): array
    {
        $tenant = Filament::getTenant();
        $events = [];

        // Rental end dates
        $rentals = Rental::where('company_id', $tenant->id)
            ->whereIn('status', ['activa', 'vencida'])
            ->whereBetween('end_date', [$fetchInfo['start'], $fetchInfo['end']])
            ->with(['customer', 'washingMachine'])
            ->get();

        foreach ($rentals as $rental) {
            $events[] = [
                'id' => 'rental-' . $rental->id,
                'title' => ($rental->customer?->name ?? 'Cliente') . ' - ' . ($rental->washingMachine?->machine_code ?? ''),
                'start' => $rental->end_date,
                'color' => $rental->status === 'vencida' ? '#ef4444' : '#06b6d4',
                'extendedProps' => [
                    'type' => 'Vencimiento de renta',
                ],
            ];
        }

        // Maintenance dates
        $maintenances = Maintenance::where('company_id', $tenant->id)
            ->where('status', 'pendiente')
            ->whereBetween('start_date', [$fetchInfo['start'], $fetchInfo['end']])
            ->with('washingMachine')
            ->get();

        foreach ($maintenances as $maintenance) {
            $events[] = [
                'id' => 'maint-' . $maintenance->id,
                'title' => 'Mant: ' . ($maintenance->washingMachine?->machine_code ?? ''),
                'start' => $maintenance->start_date,
                'end' => $maintenance->end_date,
                'color' => '#f59e0b',
                'extendedProps' => [
                    'type' => 'Mantenimiento',
                ],
            ];
        }

        return $events;
    }

    public function config(): array
    {
        return [
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,listWeek',
            ],
            'locale' => 'es',
            'firstDay' => 1,
            'height' => 'auto',
        ];
    }
}
