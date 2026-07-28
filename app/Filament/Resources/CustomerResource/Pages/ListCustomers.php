<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Actions\CreateWithinPlanAction;
use App\Filament\Resources\CustomerResource;
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
}
