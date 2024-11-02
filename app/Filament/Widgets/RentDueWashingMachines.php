<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\WashingMachineResource;
use App\Filament\Resources\RentalResource;
use App\Models\Rental;
use App\Models\WashingMachine;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Tables\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Notifications\Actions\Action as NotificationAction;

class RentDueWashingMachines extends BaseWidget
{
    protected static ?int $sort = 6;
    protected static ?string $heading = 'Rentas por Vencer';
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $tenant = Filament::getTenant();
        return $table
            ->query(
                RentalResource::getEloquentQuery()
                    ->where('status', 'activa')
                    ->where('end_date', '<', Carbon::now()->addDays(3))
            )
            ->paginated(false)
            ->defaultSort('end_date', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer'),
                Tables\Columns\TextColumn::make('washingMachine.machine_code')
                    ->label('Washing Machine'),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('End Date')
                    ->date(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])->actions([
                Tables\Actions\Action::make('extend_rent')
                    ->label('Extender Renta')
                    ->icon('heroicon-o-calendar')
                    ->requiresConfirmation()
                    ->action(function (array $data, Rental $record) use ($tenant) {

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

                        $newDate = new Carbon($record->end_date);
                        $newDate->add($days, 'days');
                        $record->end_date = $newDate->format('Y-m-d');
                        if($record->status === 'vencida') {
                            $record->status = 'activa';
                        }
                        $record->save();

                        Notification::make()
                            ->title('Se pago la renta de la lavadora.')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
