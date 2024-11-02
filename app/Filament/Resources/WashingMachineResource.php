<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Actions\ExtendRentAction;
use App\Filament\Resources\CompanyResource\Actions\RentAction;
use App\Filament\Resources\WashingMachineResource\Pages;
use App\Models\WashingMachine;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Filament\Facades\Filament;
use Filament\Tables\Actions\ActionGroup;



class WashingMachineResource extends Resource
{
    protected static ?string $model = WashingMachine::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Lavadora';
    protected static ?string $pluralModelLabel = 'Lavadoras';
    protected static ?string $navigationLabel = 'Mis Lavadoras';
    protected static ?string $slug = 'lavadoras';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('machine_code')
                    ->label('Código')
                    ->required(),
                Forms\Components\TextInput::make('brand')
                    ->label('Marca'),
                Forms\Components\TextInput::make('model')
                    ->label('Modelo'),
                Forms\Components\Select::make('status')
                    ->label('Estatus')
                    ->options([
                        'disponible' => 'Disponible',
                        'rentada' => 'Rentada',
                        'mantenimiento' => 'Mantenimiento',
                        'vendida' => 'Vendida',
                        'fuera_de_servicio' => 'Fuera de Servicio',
                    ])
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $tenant = Filament::getTenant();
        return $table
            ->columns([
                //
                Tables\Columns\TextColumn::make('machine_code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('brand')
                    ->label('Marca')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('model')
                    ->label('Modelo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'disponible' => 'primary',
                        'rentada' => 'success',
                        'mantenimiento' => 'gray',
                        'vendida' => 'info',
                        'fuera_de_servicio' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('rental.status')
                    ->label('Estatus Renta')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'activa' => 'primary',
                        'vencida' => 'danger',
                        'completada' => 'info',
                        'cancelada' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('rental.customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rental.start_date')
                    ->label('Fecha de Inicio')
                    ->date()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rental.end_date')
                    ->label('Fecha de Fin')
                    ->date()
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
                SelectFilter::make('status')
                    ->options([
                        'disponible' => 'Disponible',
                        'rentada' => 'Rentada',
                        'mantenimiento' => 'Mantenimiento',
                        'vendida' => 'Vendida',
                        'fuera_de_servicio' => 'Fuera de Servicio',
                    ])
                    ->label('Status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                ActionGroup::make([
                    RentAction::make($tenant),
                    ExtendRentAction::make($tenant),
                    Tables\Actions\Action::make('make_available')
                        ->visible(fn (WashingMachine $record) => in_array($record->status, ['rentada', 'en_mantenimiento']) )
                        ->label('Marcar Disponible')
                        ->icon('heroicon-s-check-circle')
                        ->slideOver()
                        ->requiresConfirmation()
                        ->action(function (array $data, WashingMachine $record) use ($tenant) {
                            $record->update(['status' => 'disponible']);
                            $record->rentals()->where('status', 'activa')->first()->update(['status' => 'cancelada', 'end_date' => new Carbon()]);
                            Notification::make()
                                ->title('La lavadora esta disponible y la renta ha sido cancelada')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('make_maintenance')
                        ->visible(fn (WashingMachine $record) => !in_array($record->status, ['mantenimiento']) )
                        ->label('Enviar a Mantenimiento')
                        ->icon('heroicon-s-wrench-screwdriver')
                        ->form([
                            Forms\Components\TextInput::make('technician_name')
                                ->label('Técnico')
                                ->required(),
                            Forms\Components\DatePicker::make('start_date')
                                ->label('Fecha de Inicio')
                                ->required(),
                            Forms\Components\Select::make('maintenance_type')
                                ->label('Tipo de Mantenimiento')
                                ->options([
                                    'preventivo' => 'Preventivo',
                                    'correctivo' => 'Correctivo',
                                ])
                                ->required(),
                            Forms\Components\Textarea::make('description')
                                ->label('Descripción')
                                ->required(),
                            Forms\Components\TextInput::make('cost')
                                ->label('Costo')
                                ->required(),
                        ])
                        ->action(function (array $data, WashingMachine $record) use ($tenant) {
                            $data['washing_machine_id'] = $record->id;
                            // Check date if it is today
                            if($data['start_date'] === Carbon::now()->format('Y-m-d')) {
                                $data['status'] = 'en_progreso';
                            } else {
                                $data['status'] = 'programada';
                            }
                            $maintenance = $tenant->maintenances()->create($data);
                            $record->update(['status' => 'mantenimiento']);


                            Notification::make()
                                ->title('La lavadora ha sido enviada a mantenimiento')
                                ->success()
                                ->send();
                        }),
                        Tables\Actions\Action::make('make_active')
                            ->visible(fn (WashingMachine $record) => in_array($record->status, ['mantenimiento']) )
                            ->label('Activar Lavadora')
                            ->icon('heroicon-s-wrench-screwdriver')
                            ->requiresConfirmation()
                            ->action(function (array $data, WashingMachine $record) use ($tenant) {

                                $maintenance = $record->maintenances()->where('status', 'en_progreso')->first();
                                $maintenance->completeMaintenance();
                                $rental = $record->rentals()->where('status', 'activa')->first();
                                if($rental) {
                                    $days = $maintenance->getDurationInDays();
                                    if($days > 0) {
                                        $newDate = new Carbon($rental->end_date);
                                        $newDate->add($days, 'days');
                                        $rental->end_date = $newDate->format('Y-m-d');
                                        $rental->save();
                                    }
                                    $record->update(['status' => 'rentada']);
                                } else {
                                    $record->update(['status' => 'disponible']);
                                }

                                Notification::make()
                                    ->title('La lavadora esta disponible y el mantenimiento ha sido completado')
                                    ->success()
                                    ->send();
                            }),
                ]),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWashingMachines::route('/'),
            'create' => Pages\CreateWashingMachine::route('/create'),
            'edit' => Pages\EditWashingMachine::route('/{record}/edit'),
        ];
    }

}
