<?php

namespace App\Filament\Resources\MaintenanceResource\Pages;

use App\Filament\Resources\MaintenanceResource;
use App\Filament\Resources\MaintenanceResource\Widgets\MantenimientosStats;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaintenances extends ListRecords
{
    protected static string $resource = MaintenanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [MantenimientosStats::class];
    }
}
