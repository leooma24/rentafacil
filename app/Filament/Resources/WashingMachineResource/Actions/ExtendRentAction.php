<?php

namespace App\Filament\Resources\CompanyResource\Actions;

use Filament\Notifications\Actions\Action as NotificationAction;
use App\Models\WashingMachine;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Facades\Filament;
use Filament\Tables;
use App\Models\Company;
use Filament\Forms;

class ExtendRentAction
{
    public static function make(Company $tenant): Tables\Actions\Action
    {
        $tenant = Filament::getTenant();
        return Tables\Actions\Action::make('extend_rent')
            ->visible(fn(WashingMachine $record) => $record->status === 'rentada')
            ->label('Extender Renta')
            ->icon('heroicon-o-calendar')
            ->form([
                //
                Forms\Components\TextInput::make('price')
                    ->label('Precio de renta')
                    ->default($tenant->settings?->price),
                Forms\Components\TextInput::make('days')
                    ->label('Días de renta')
                    ->default($tenant->settings?->days_per_payment),

            ])
            ->action(function (array $data, WashingMachine $record) use ($tenant) {
                $rental = $record->rentals()->whereIn('status', ['activa', 'vencida'])->first();

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

                if ($rental->status === 'vencida') {
                    $rental->status = 'activa';
                    $rental->save();
                }

                Notification::make()
                    ->title('Se pago la renta de la lavadora ' . $record->machine_code)
                    ->success()
                    ->send();
            });
    }
}
