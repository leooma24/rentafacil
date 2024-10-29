<?php

namespace App\Filament\Resources\WashingMachineResource\Pages;

use App\Filament\Resources\WashingMachineResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWashingMachine extends EditRecord
{
    protected static string $resource = WashingMachineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
