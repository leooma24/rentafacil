<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\ExpenseResource\Widgets\GastosStats;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Registrar gasto'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [GastosStats::class];
    }

    public function getSubheading(): ?string
    {
        return 'Lo que sale del negocio. Sin esto, lo que cobras se lee como ganancia y no lo es: falta la gasolina, los sueldos y las refacciones.';
    }
}
