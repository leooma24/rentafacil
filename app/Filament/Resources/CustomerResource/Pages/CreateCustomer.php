<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function beforeFill(): void
    {
        $tenant = Filament::getTenant();
        if(!$tenant->canAddMoreClients()) {
            Notification::make()
                ->warning()
                ->title('Has alcanzado el límite de clientes')
                ->body('Favor de contactar a soporte para aumentar el límite de clientes')
                ->persistent()
                ->send();
            $this->redirect($this->getResource()::getUrl('index'));

        }
    }
}
