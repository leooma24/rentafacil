<?php

namespace App\Filament\Resources\RentalResource\Actions;

use Filament\Notifications\Actions\Action as NotificationAction;
use App\Models\Rental;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Tables;
use App\Models\Company;
use App\Support\RentalTerms;
use App\Support\ShareableLinks;
use Filament\Forms;

class ExtendRentAction
{
    public static function make(Company $tenant): Tables\Actions\Action
    {
        $terms = RentalTerms::for($tenant);

        return Tables\Actions\Action::make('extend_rent')
            ->label('Extender Renta')
            ->icon('heroicon-o-calendar')
            ->modalSubmitActionLabel('Cobrar')
            // El camino normal es confirmar: los seis campos ya vienen llenos y
            // esto se usa parado en la puerta del cliente, desde el celular.
            ->modalHeading(fn (Rental $rental) => 'Cobrar ' . $terms->summary())
            ->modalDescription(fn (Rental $rental) => $terms->isConfigured()
                ? 'Se registra hoy y la renta se extiende al '
                    . $terms->endDateFrom($rental->end_date)->format('d/m/Y') . '.'
                : 'Falta configurar tu precio de renta en Preferencias.')
            ->form([
                Forms\Components\Section::make('Cambiar monto o método')
                    ->description('Solo si este cobro es distinto al de siempre.')
                    ->icon('heroicon-o-pencil-square')
                    ->collapsed()
                    ->collapsible()
                    ->columns('3')
                    ->schema([
                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Fecha de Pago')
                            ->default(now()),
                        Forms\Components\TextInput::make('price')
                            ->label('Precio de renta')
                            ->default($terms->price),
                        Forms\Components\TextInput::make('days')
                            ->label('Días de renta')
                            ->default($terms->days),
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

                $payment = $rental->payments()->create([
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

                $telefono = $rental->customer?->phone;

                Notification::make()
                    ->title('Cobro registrado: ' . $rental->washingMachine->machine_code)
                    ->body($telefono ? 'Puedes mandarle su comprobante por WhatsApp.' : null)
                    ->success()
                    ->persistent()
                    // El comprobante se manda en el momento, que es cuando el
                    // cliente lo espera y cuando tienes su teléfono a la mano.
                    ->actions(array_filter([
                        $telefono
                            ? NotificationAction::make('Mandar recibo')
                                ->button()
                                ->url(ShareableLinks::whatsappUrl(
                                    $telefono,
                                    ShareableLinks::receiptMessage($payment->fresh('rental'))
                                ))
                                ->openUrlInNewTab()
                            : null,
                    ]))
                    ->send();
            });
    }
}
