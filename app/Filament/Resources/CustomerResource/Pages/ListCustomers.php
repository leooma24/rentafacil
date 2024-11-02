<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Facades\Filament;


class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        $tenant = Filament::getTenant();
        if($tenant->canAddMoreClients()) {
            return [
                Actions\CreateAction::make(),
            ];
        }
        return [];
    }
}
