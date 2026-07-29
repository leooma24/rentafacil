<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenanceResource\Pages;
use App\Filament\Resources\MaintenanceResource\RelationManagers;
use App\Models\Maintenance;
use App\Models\WashingMachine;
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

    protected static ?string $navigationGroup = 'Servicios';
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
                Forms\Components\Section::make('Qué equipo y quién lo vio')
                    ->description('Mientras esté en mantenimiento no aparece para rentar.')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->iconColor('primary')
                    ->schema([
                        Forms\Components\Select::make('washing_machine_id')
                            ->label('Equipo')
                            ->options(function ($record) use ($tenant) {
                                $options = $tenant->washingMachines()
                                    ->where('status', '!=', 'mantenimiento');

                                if ($record) {
                                    $options->orWhere('id', $record->washing_machine_id);
                                }

                                // Con secadoras en el parque, el puro código ya
                                // no dice a qué aparato se le está abriendo la
                                // orden.
                                return $options->orderBy('machine_code')->get()
                                    ->mapWithKeys(fn ($equipo) => [
                                        $equipo->id => "{$equipo->machine_code} · {$equipo->kindLabel()}"
                                            . ($equipo->brand ? " {$equipo->brand}" : ''),
                                    ]);
                            })
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('technician_name')
                            ->label('Quién lo revisó')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon('heroicon-o-user')
                            ->datalist(fn () => $tenant->maintenances()
                                ->whereNotNull('technician_name')
                                ->distinct()
                                ->pluck('technician_name')
                                ->all())
                            ->helperText('Se te sugieren los técnicos que ya has usado.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Qué se le hizo')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->iconColor('warning')
                    ->schema([
                        Forms\Components\Select::make('maintenance_type')
                            ->label('Tipo')
                            ->options([
                                'preventivo' => 'Preventivo — revisión de rutina',
                                'correctivo' => 'Correctivo — se descompuso',
                            ])
                            ->native(false)
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Cómo va')
                            ->options([
                                'programada' => 'Programada',
                                'en_progreso' => 'En proceso',
                                'completado' => 'Terminado',
                            ])
                            ->default('programada')
                            ->native(false)
                            ->live()
                            ->required(),

                        Forms\Components\Textarea::make('description')
                            ->label('Qué se le hizo')
                            ->placeholder('Cambio de banda del motor y limpieza de filtros.')
                            ->rows(3)
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Cuándo y cuánto')
                    ->description('El costo entra a tus gastos del mes: sin él, la ganancia que ves está inflada.')
                    ->icon('heroicon-o-banknotes')
                    ->iconColor('success')
                    ->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Entró a taller')
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->format('Y-m-d')
                            ->required(),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('Salió')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->format('Y-m-d')
                            // No puede salir antes de entrar. Así se le colaron
                            // al demo unos mantenimientos que duraban en
                            // negativo, y el promedio de días salía absurdo.
                            ->afterOrEqual('start_date')
                            ->helperText('Déjala vacía mientras siga en el taller.'),

                        // El costo existía en la base y no en el formulario: no
                        // había forma de anotar lo que costó una reparación.
                        Forms\Components\TextInput::make('cost')
                            ->label('Qué costó')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->prefix('$')
                            ->suffix('MXN')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
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
                    ->date('d/m/Y')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fecha de fin')
                    ->date('d/m/Y')
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
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '—')
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
                        ->visible(fn(Maintenance $record) => !in_array($record->status, ['completado']))
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
                            if ($rental) {
                                $days = $record->getDurationInDays();
                                if ($days > 0) {
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
