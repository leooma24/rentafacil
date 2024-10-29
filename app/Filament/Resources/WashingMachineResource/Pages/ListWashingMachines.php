<?php

namespace App\Filament\Resources\WashingMachineResource\Pages;

use App\Filament\Resources\WashingMachineResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListWashingMachines extends ListRecords
{
    protected static string $resource = WashingMachineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(),
            'disponible' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'disponible')),
            'rentada' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'rentada')),
            'mantenimiento' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'mantenimiento')),
            'vendida' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'vendida')),
            'fuera_de_servicio' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'fuera_de_servicio')),
        ];
    }
}
