<?php

namespace App\Filament\Resources\RentalResource\Actions;

use Filament\Notifications\Actions\Action as NotificationAction;
use App\Models\Rental;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Tables;
use App\Models\Company;
use Filament\Forms;

class ExtendRentAction
{
    public static function make(Company $tenant): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('extend_rent')
            ->label('Extender Renta')
            ->icon('heroicon-o-calendar')
            ->modalSubmitActionLabel('Pagar')
            ->form([
                //
                Forms\Components\Section::make('Extender Renta')
                    ->description('Formulario para extender la renta de la lavadora')
                    ->icon('heroicon-o-calendar')
                    ->columns('3')
                    ->schema([
                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Fecha de Pago')
                            ->default(now()),
                        Forms\Components\TextInput::make('price')
                            ->label('Precio de renta')
                            ->default($tenant->settings?->price),
                        Forms\Components\TextInput::make('days')
                            ->label('Días de renta')
                            ->default($tenant->settings?->days_per_payment),
                        Forms\Components\Select::make('payment_method')
                            ->label('Método de Pago')
                            ->options([
                                'Tarjeta de Crédito' => 'Tarjeta de Crédito',
                                'Tarjeta de Débito' => 'Tarjeta de Débito',
                                'PayPal' => 'PayPal',
                                'Transferencia Bancaria' => 'Transferencia Bancaria',
                                'Efectivo' => 'Efectivo',
                            ])
                            ->default('Efectivo')
                            ->required(),
                        Forms\Components\TextInput::make('reference')
                            ->label('Referencia')
                            ->nullable(),
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'pendiente' => 'Pendiente',
                                'completado' => 'Completado',
                                'fallido' => 'Fallido',
                            ])
                            ->required()
                            ->default('completado'),
                    ]),

            ])
            ->action(function (array $data, Rental $rental) use ($tenant) {

                $days = $data['days'];
                $price = $data['price'];
                if (!$days || !$price) {
                    Notification::make()
                        ->title('No se puede extender la renta, no hay configuración de precios')
                        ->danger()
                        ->actions([
                            NotificationAction::make('Configurar')
                                ->button()
                                ->url('/propietario/' . $tenant->id . '/configuracion'),
                        ])
                        ->send();
                    return;
                }

                $newDate = new Carbon($rental->end_date);
                $newDate->add($days, 'days');
                $rental->end_date = $newDate->format('Y-m-d');
                $rental->save();

                $rental->payments()->create([
                    'company_id' => $tenant->id,
                    'amount' => $price,
                    'payment_date' => $data['payment_date'],
                    'payment_method' => $data['payment_method'],
                    'reference' => $data['reference'],
                    'status' => $data['status'],
                ]);

                if ($rental->status === 'vencida') {
                    $rental->status = 'activa';
                    $rental->save();
                }

                Notification::make()
                    ->title('Se pago la renta de la lavadora ' . $rental->washingMachine->machine_code)
                    ->success()
                    ->send();
            });
    }
}
