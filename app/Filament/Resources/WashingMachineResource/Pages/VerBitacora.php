<?php

namespace App\Filament\Resources\WashingMachineResource\Pages;

use App\Filament\Resources\WashingMachineResource;
use App\Support\BitacoraDelEquipo;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;

/**
 * Toda la vida de un aparato en una pantalla.
 *
 * Estaba repartida en cuatro: quién la ha tenido en Rentas, lo que se le ha
 * reparado en Mantenimientos, lo que le pasó en Incidencias, y de dónde vino en
 * el historial de cambios. Para contestar "¿por qué esta lavadora me sale tan
 * cara?" había que abrir las cuatro y armarlo de memoria, así que no se hacía.
 */
class VerBitacora extends Page
{
    use InteractsWithRecord;

    protected static string $resource = WashingMachineResource::class;

    protected static string $view = 'filament.resources.washing-machine.bitacora';

    protected static ?string $title = 'Bitácora del equipo';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getHeading(): string
    {
        return $this->record->machine_code . ' · ' . $this->record->kindLabel();
    }

    public function getSubheading(): ?string
    {
        return collect([
            trim($this->record->brand . ' ' . $this->record->model) ?: null,
            $this->record->serial_number ? 'Serie ' . $this->record->serial_number : null,
        ])->filter()->join(' · ') ?: null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('Editar sus datos'),
        ];
    }

    public function getBitacora(): BitacoraDelEquipo
    {
        return BitacoraDelEquipo::for($this->record);
    }
}
