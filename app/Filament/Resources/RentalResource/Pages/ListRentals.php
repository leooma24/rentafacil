<?php

namespace App\Filament\Resources\RentalResource\Pages;

use App\Filament\Resources\RentalResource;
use App\Filament\Resources\RentalResource\Widgets\RentasStats;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRentals extends ListRecords
{
    protected static string $resource = RentalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [RentasStats::class];
    }
}
