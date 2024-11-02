<?php
namespace App\Filament\Resources\CompanyResource\Actions;

use Filament\Notifications\Actions\Action as NotificationAction;
use App\Models\WashingMachine;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Facades\Filament;
use Filament\Tables;
use App\Models\Company;


class ExtendRentAction
{
    public static function make(Company $tenant) : Tables\Actions\Action
    {
        $tenant = Filament::getTenant();
        return Tables\Actions\Action::make('extend_rent')
            ->visible(fn (WashingMachine $record) => $record->status === 'rentada')
            ->label('Extender Renta')
            ->icon('heroicon-o-calendar')
            ->requiresConfirmation()
            ->action(function (array $data, WashingMachine $record) use ($tenant) {
                $rental = $record->rentals()->whereIn('status', ['activa', 'vencida'])->first();

                $settings = $tenant->settings;
                $days = $settings->days_per_payment;
                $price = $settings->price;
                if(!$days || !$price) {
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

                if($rental->status === 'vencida') {
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
