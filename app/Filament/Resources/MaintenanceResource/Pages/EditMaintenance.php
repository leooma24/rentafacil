<?php

namespace App\Filament\Resources\MaintenanceResource\Pages;

use App\Filament\Resources\MaintenanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaintenance extends EditRecord
{
    protected static string $resource = MaintenanceResource::class;

    /**
     * Marcar la orden como terminada aquí tiene que soltar al equipo, igual que
     * el botón "Terminar mantenimiento" de la lista.
     *
     * Hasta que el estatus se pudo capturar en el formulario, este camino no
     * existía. Al abrirlo quedaba la trampa: se daba por terminada la
     * reparación, el equipo seguía marcado en mantenimiento y ya no aparecía
     * para rentar, sin ninguna orden abierta que lo explicara.
     */
    protected function afterSave(): void
    {
        if ($this->record->status === 'completado') {
            $this->record->devolverEquipoACirculacion();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            //Actions\DeleteAction::make(),
        ];
    }
}
