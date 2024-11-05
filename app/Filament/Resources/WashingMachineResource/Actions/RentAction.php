<?php
namespace App\Filament\Resources\CompanyResource\Actions;

use App\Events\RentEvent;
use Filament\Tables;
use App\Models\WashingMachine;
use Filament\Forms;
use Filament\Notifications\Notification;

class RentAction {
    public static function make($tenant) {
        return Tables\Actions\Action::make('rent', 'Rentar')
        ->visible(fn (WashingMachine $record) => $record->status === 'disponible')
        ->icon('heroicon-s-currency-dollar')
        ->label('Rentar')
        ->slideOver()
        ->modalWidth('md')
        ->form([
            Forms\Components\Select::make('customer_id')
                ->label('Customer')
                ->options(
                    \App\Models\Customer::where('company_id', $tenant->id)->pluck('name', 'id')
                )
                ,
            Forms\Components\DatePicker::make('start_date')
                ->label('Fecha de Inicio')
                ->required(),
            Forms\Components\DatePicker::make('end_date')
                ->label('Fecha de Fin')
                ->required(),
            Forms\Components\Select::make('status')
                ->label('Estatus')
                ->options([
                    'activa' => 'Activa',
                    'completa' => 'Completa',
                    'cancelada' => 'Cancelada',
                ])
                ->default('activa')
                ->required(),
            Forms\Components\Textarea::make('notes')
                ->label('Notas')
                ->columnSpanFull(),
        ])->action(function (array $data, WashingMachine $record) use ($tenant) {
            $data['washing_machine_id'] = $record->id;
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
