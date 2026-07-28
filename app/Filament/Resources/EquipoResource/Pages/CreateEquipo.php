<?php

namespace App\Filament\Resources\EquipoResource\Pages;

use App\Filament\Resources\EquipoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEquipo extends CreateRecord
{
    protected static string $resource = EquipoResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
