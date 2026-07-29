<?php

namespace App\Filament\Resources\MaintenanceResource\Pages;

use App\Filament\Resources\MaintenanceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Carbon\Carbon;

class CreateMaintenance extends CreateRecord
{
    protected static string $resource = MaintenanceResource::class;

    /**
     * Antes esto pisaba el estatus SIEMPRE, así que capturarlo no servía de
     * nada: se anotaba una reparación ya terminada y quedaba como programada. Y
     * mandaba el equipo a mantenimiento aunque el trabajo ya estuviera hecho,
     * con lo que apuntar una compostura vieja sacaba el aparato de circulación.
     *
     * Ahora manda lo que se capturó. Del comportamiento anterior sólo se
     * conserva el atajo: una orden programada que arranca hoy ya está en
     * proceso, y no tiene caso obligar a decirlo dos veces.
     */
    public function afterCreate(): void
    {
        if ($this->record->status === 'programada'
            && $this->record->start_date === Carbon::now()->format('Y-m-d')) {
            $this->record->update(['status' => 'en_progreso']);
        }

        // Un trabajo ya terminado no saca de circulación al equipo.
        if ($this->record->status !== 'completado') {
            $this->record->washingMachine?->update(['status' => 'mantenimiento']);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
