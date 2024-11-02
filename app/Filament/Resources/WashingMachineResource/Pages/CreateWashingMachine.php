<?php

namespace App\Filament\Resources\WashingMachineResource\Pages;

use App\Filament\Resources\WashingMachineResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

class CreateWashingMachine extends CreateRecord
{
    protected static string $resource = WashingMachineResource::class;

    protected function beforeFill(): void
    {
        $tenant = Filament::getTenant();
        if(!$tenant->canAddMoreClients()) {
            Notification::make()
                ->warning()
                ->title('Has alcanzado el límite de Lavadoras')
                ->body('Favor de contactar a soporte para aumentar el límite de lavadoras')
                ->persistent()
                ->send();
            $this->redirect($this->getResource()::getUrl('index'));

        }
    }
}
