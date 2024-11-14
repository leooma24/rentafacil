<?php

namespace App\Filament\Resources\ProspectiveClientResource\Pages;

use App\Filament\Resources\ProspectiveClientResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProspectiveClient extends EditRecord
{
    protected static string $resource = ProspectiveClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
