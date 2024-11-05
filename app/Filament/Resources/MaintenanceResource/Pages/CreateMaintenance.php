<?php

namespace App\Filament\Resources\MaintenanceResource\Pages;

use App\Filament\Resources\MaintenanceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Carbon\Carbon;

class CreateMaintenance extends CreateRecord
{
    protected static string $resource = MaintenanceResource::class;

    public function afterCreate()
    {
        $estatus ='programada';
        if($this->record->start_date === Carbon::now()->format('Y-m-d')) {
            $estatus = 'en_progreso';
        }
        $this->record->update(['status' => $estatus]);

        $this->record->washingMachine->update(['status' => 'mantenimiento']);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
