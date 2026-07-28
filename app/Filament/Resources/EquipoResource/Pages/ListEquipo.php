<?php

namespace App\Filament\Resources\EquipoResource\Pages;

use App\Filament\Resources\EquipoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEquipo extends ListRecords
{
    protected static string $resource = EquipoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Dar de alta'),
        ];
    }

    public function getSubheading(): ?string
    {
        return 'Quién más entra a tu sistema. Un cobrador ve a quién cobrar y registra los pagos, pero no puede cambiar precios ni ver tus reportes.';
    }
}
