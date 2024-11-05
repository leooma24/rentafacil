<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenanceResource\Pages;
use App\Filament\Resources\MaintenanceResource\RelationManagers;
use App\Models\Maintenance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use Filament\Tables\Actions\ActionGroup;
use Carbon\Carbon;
use Filament\Notifications\Notification;

class MaintenanceResource extends Resource
{
    protected static ?string $model = Maintenance::class;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationIcon = 'heroicon-s-wrench-screwdriver';
    protected static ?string $modelLabel = 'Mantenimiento';
    protected static ?string $pluralModelLabel = 'Mantenimientos';
    protected static ?string $navigationLabel = 'Mantenimientos';
    protected static ?string $slug = 'mantenimientos';

    public static function form(Form $form): Form
    {
        $tenant = Filament::getTenant();
        return $form
            ->schema([
                //
                Forms\Components\Select::make('washing_machine_id')
                    ->label('Lavadora')
                    ->options(
                        $tenant->washingMachines()->where('status', '!=', 'mantenimiento')->pluck('machine_code', 'id')
                    )
                    ->required(),
                Forms\Components\TextInput::make('technician_name')
                    ->label('Técnico')
                    ->required(),
                Forms\Components\DatePicker::make('start_date')
                    ->label('Fecha de Inicio')
                    ->default(now())
                    ->required(),
                Forms\Components\DatePicker::make('end_date')
                    ->label('Fecha de Fin'),
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
            ]);
    }

    public static function table(Table $table): Table
    {
        $tenant = Filament::getTenant();
        return $table
            ->columns([
                //
                Tables\Columns\TextColumn::make('technician_name')
                    ->label('Técnico')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Fecha de Inicio')
                    ->date()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fecha de fin')
                    ->date()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('maintenance_type')
                    ->label('Tipo de Mantenimiento')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción'),
                Tables\Columns\TextColumn::make('cost')
                    ->label('Costo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                ActionGroup::make([
                    Tables\Actions\Action::make('make_maintenance')
                        ->visible(fn (Maintenance $record) => !in_array($record->status, ['completado']) )
                        ->label('Terminar Mantenimiento')
                        ->slideOver()
                        ->modalWidth('md')
                        ->modalSubmitActionLabel('Terminar')
                        ->form([
                            Forms\Components\TextInput::make('cost')
                                ->label('Costo')
                                ->required(),
                        ])->action(function (array $data, Maintenance $record) use ($tenant) {
                            $record->update([
                                'status' => 'completado',
                                'end_date' => now(),
                                'cost' => $data['cost']
                            ]);

                            $rental = $record->washingMachine->rentals()->where('status', 'activa')->first();
                            if($rental) {
                                $days = $record->getDurationInDays();
                                if($days > 0) {
                                    $newDate = new Carbon($rental->end_date);
                                    $newDate->add($days, 'days');
                                    $rental->end_date = $newDate->format('Y-m-d');
                                    $rental->save();
                                }
                                $record->washingMachine->update(['status' => 'rentada']);
                            } else {
                                $record->washingMachine->update(['status' => 'disponible']);
                            }

                            Notification::make()
                                    ->title('Mantenimiento Terminado')
                                    ->success()
                                    ->send();

                        })
                ])

            ])
            /*->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])*/;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaintenances::route('/'),
            'create' => Pages\CreateMaintenance::route('/create'),
            'edit' => Pages\EditMaintenance::route('/{record}/edit'),
        ];
    }

}
