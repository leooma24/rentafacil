<?php

namespace App\Filament\Resources\CustomerResource\Widgets;

use App\Filament\Resources\Widgets\CatalogoStats;
use App\Support\AccountStatement;
use App\Support\Statement;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClientesStats extends CatalogoStats
{
    protected function getStats(): array
    {
        $tenant = $this->tenant();

        $total = $tenant->customers()->count();

        $conRenta = $tenant->customers()
            ->whereHas('rentals', fn ($query) => $query->whereIn('status', ['activa', 'vencida']))
            ->count();

        // La misma fuente que el estado de cuenta y el escritorio: si el adeudo
        // se calculara aparte, tarde o temprano diría otra cosa.
        $estados = app(AccountStatement::class)->forCompany($tenant);
        $deben = $estados->count();
        $adeudo = (float) $estados->sum(fn (Statement $estado) => $estado->total);

        $sinRenta = $total - $conRenta;

        return [
            Stat::make('Con renta', $conRenta)
                ->description("de {$total} clientes registrados")
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),

            Stat::make('Te deben', $deben)
                ->description($deben > 0 ? $this->dinero($adeudo) . ' en total' : 'nadie con saldo pendiente')
                ->descriptionIcon($deben > 0 ? 'heroicon-m-banknotes' : 'heroicon-m-check-circle')
                ->color($deben > 0 ? 'danger' : 'success'),

            // No es un dato de adorno: es la lista de a quién volver a marcarle.
            Stat::make('Sin renta', $sinRenta)
                ->description($sinRenta > 0 ? 'clientes por reactivar' : 'todos con lavadora')
                ->descriptionIcon($sinRenta > 0 ? 'heroicon-m-phone-arrow-up-right' : 'heroicon-m-check-circle')
                ->color($sinRenta > 0 ? 'warning' : 'success'),
        ];
    }
}
