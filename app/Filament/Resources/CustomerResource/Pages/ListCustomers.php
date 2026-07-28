<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Actions\CreateWithinPlanAction;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Widgets\ClientesStats;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Facades\Filament;


class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        // El botón siempre está: si ya no hay cupo, explica el límite en vez de
        // desaparecer y dejar al dueño pensando que la app se descompuso.
        return [
            CreateWithinPlanAction::make(Filament::getTenant(), 'clientes'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [ClientesStats::class];
    }

    // Dice para qué sirve la pantalla en términos de lo que el dueño gana. De
    // 17 cuentas reales, la mitad nunca pasó de aquí: el catálogo se explicaba
    // solo a quien ya sabía qué buscar.
    public function getSubheading(): ?string
    {
        return 'A quién le rentas. Abre uno para ver su estado de cuenta y mandárselo por WhatsApp.';
    }
}
