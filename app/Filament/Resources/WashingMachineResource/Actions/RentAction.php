<?php

namespace App\Filament\Resources\WashingMachineResource\Actions;

use App\Events\RentEvent;
use Filament\Tables;
use App\Models\WashingMachine;
use Filament\Forms;
use Filament\Notifications\Notification;

class RentAction
{
    public static function make($tenant)
    {
        $terms = \App\Support\RentalTerms::for($tenant);

        return Tables\Actions\Action::make('rent', 'Rentar')
            ->visible(fn(WashingMachine $record) => $record->status === 'disponible')
            ->icon('heroicon-s-currency-dollar')
            ->label('Rentar')
            ->slideOver()
            ->modalWidth('md')
            ->modalSubmitActionLabel('Rentar')
            ->form([
                // El cliente iba sin required, y como rentals.customer_id no
                // admite nulos, mandar el formulario vacío tronaba con un error
                // de base de datos en la cara del usuario.
                Forms\Components\Select::make('customer_id')
                    ->label('Cliente')
                    ->options(
                        \App\Models\Customer::where('company_id', $tenant->id)->pluck('name', 'id')
                    )
                    ->searchable()
                    ->required(),
                // Las fechas llegan resueltas: se entrega hoy y se cubre el
                // periodo que la empresa tenga configurado.
                Forms\Components\DatePicker::make('start_date')
                    ->label('Fecha de Inicio')
                    ->default(now())
                    ->required(),
                Forms\Components\DatePicker::make('end_date')
                    ->label('Fecha de Fin')
                    ->default($terms->endDateFrom())
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->label('Notas')
                    ->columnSpanFull(),
            ])->action(function (array $data, WashingMachine $record) use ($tenant) {
                $data['washing_machine_id'] = $record->id;
                // Toda entrega nace activa: preguntarlo solo daba pie a errores.
                $data['status'] = 'activa';
                $rental = $tenant->rentals()->create($data);

                $record->update(['status' => 'rentada']);

                $data = [
                    'email' => $rental->customer->email,
                    'nombre' => $rental->customer->name,
                    'mensaje' => 'Has rentado ' . $record->name . ', el día ' . $rental->start_date . ' enviaremos un instalador a su domicilio.'
                ];

                event(new RentEvent($data));

                Notification::make()
                    ->title('La lavadora ha sido rentada')
                    ->success()
                    ->send();
            });
    }
}
