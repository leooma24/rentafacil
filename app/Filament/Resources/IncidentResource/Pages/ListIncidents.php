<?php

namespace App\Filament\Resources\IncidentResource\Pages;

use App\Filament\Resources\IncidentResource;
use App\Filament\Resources\IncidentResource\Widgets\IncidenciasStats;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListIncidents extends ListRecords
{
    protected static string $resource = IncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [IncidenciasStats::class];
    }
}
