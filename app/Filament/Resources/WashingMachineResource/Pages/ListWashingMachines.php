<?php

namespace App\Filament\Resources\WashingMachineResource\Pages;

use App\Filament\Actions\CreateWithinPlanAction;
use App\Filament\Resources\WashingMachineResource;
use App\Filament\Resources\WashingMachineResource\Widgets\LavadorasStats;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Filament\Facades\Filament;

class ListWashingMachines extends ListRecords
{
    protected static string $resource = WashingMachineResource::class;

    protected function getHeaderActions(): array
    {
        // El botón siempre está: si ya no hay cupo, explica el límite en vez de
        // desaparecer y dejar al dueño pensando que la app se descompuso.
        return [
            CreateWithinPlanAction::make(Filament::getTenant(), 'lavadoras'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [LavadorasStats::class];
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
