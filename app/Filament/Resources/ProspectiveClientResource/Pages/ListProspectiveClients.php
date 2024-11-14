<?php

namespace App\Filament\Resources\ProspectiveClientResource\Pages;

use App\Filament\Resources\ProspectiveClientResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProspectiveClients extends ListRecords
{
    protected static string $resource = ProspectiveClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
