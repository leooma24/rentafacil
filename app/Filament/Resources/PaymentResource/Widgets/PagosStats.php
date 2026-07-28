<?php

namespace App\Filament\Resources\PaymentResource\Widgets;

use App\Filament\Resources\Widgets\CatalogoStats;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PagosStats extends CatalogoStats
{
    protected function getStats(): array
    {
        $hoy = (clone $this->pagos())->whereDate('payment_date', today());
        $cobradoHoy = (float) (clone $hoy)->sum('amount');
        $cobrosHoy = (clone $hoy)->count();

        $mes = fn () => (clone $this->pagos())
            ->whereYear('payment_date', now()->year)
            ->whereMonth('payment_date', now()->month);

        $cobradoMes = (float) $mes()->sum('amount');
        $cobrosMes = $mes()->count();

        // Cuánto de lo cobrado anda en efectivo: es lo que trae el cobrador
        // encima y lo que hay que depositar. En transferencia ya está en banco.
        $efectivo = (float) $mes()->where('payment_method', 'Efectivo')->sum('amount');
        $porcentaje = $cobradoMes > 0 ? round($efectivo / $cobradoMes * 100) : 0;

        return [
            Stat::make('Cobrado hoy', $this->dinero($cobradoHoy))
                ->description($cobrosHoy === 1 ? '1 cobro registrado' : "{$cobrosHoy} cobros registrados")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($cobradoHoy > 0 ? 'success' : 'warning'),

            Stat::make('Cobrado este mes', $this->dinero($cobradoMes))
                ->description("{$cobrosMes} cobros en el mes")
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),

            Stat::make('En efectivo', $this->dinero($efectivo))
                ->description("{$porcentaje}% de lo cobrado este mes")
                ->descriptionIcon('heroicon-m-wallet')
                ->color($porcentaje >= 50 ? 'warning' : 'success'),
        ];
    }
}
