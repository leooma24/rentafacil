<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WashingMachineResource\Actions;
use App\Filament\Resources\WashingMachineResource\Pages;
use App\Imports\WashingMachinesImport;
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
use Maatwebsite\Excel\Facades\Excel;



class WashingMachineResource extends Resource
{
    protected static ?string $model = WashingMachine::class;

    protected static ?string $navigationIcon = 'heroicon-s-archive-box';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationGroup = 'Gestión Principal';
    // "Equipos" y no "Lavadoras": el negocio también renta secadoras. El slug se
    // queda en /lavadoras para no romper los enlaces que ya andan por ahí.
    protected static ?string $modelLabel = 'Equipo';
    protected static ?string $pluralModelLabel = 'Equipos';
    protected static ?string $navigationLabel = 'Equipos';
    protected static ?string $slug = 'lavadoras';
    protected static ?string $recordTitleAttribute = 'machine_code';

    public static function getGloballySearchableAttributes(): array
    {
        return ['machine_code', 'brand', 'model', 'serial_number'];
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Marca' => $record->brand . ' ' . $record->model,
            'Estado' => ucfirst($record->status),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información Básica')
                    ->description('Datos generales del equipo')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Forms\Components\Select::make('kind')
                            ->label('Qué es')
                            ->options(WashingMachine::KINDS)
                            ->default('lavadora')
                            ->required()
                            ->native(false)
                            ->helperText('Lavadora, secadora o las dos en un mismo aparato.'),
                        Forms\Components\TextInput::make('machine_code')
                            ->label('Código')
                            ->required(),
                        Forms\Components\TextInput::make('brand')
                            ->label('Marca'),
                        Forms\Components\TextInput::make('model')
                            ->label('Modelo'),
                        Forms\Components\TextInput::make('serial_number')
                            ->label('Número de serie')
                            ->nullable()
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Fecha de compra')
                            ->nullable(),
                        Forms\Components\TextInput::make('purchase_price')
                            ->label('Precio de compra')
                            ->nullable()
                            ->numeric(),
                        // Cómo carga, no qué es: eso ahora vive en kind.
                        Forms\Components\Select::make('type')
                            ->label('Cómo carga')
                            ->options([
                                'Carga frontal' => 'Carga frontal',
                                'Carga superior' => 'Carga superior',
                            ])
                            ->nullable(),
                        Forms\Components\TextInput::make('color')
                            ->label('Color')
                            ->nullable()
                            ->maxLength(100),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Dimensiones y Peso')
                    ->description('Medidas y peso de la lavadora')
                    ->icon('heroicon-o-scale')
                    ->schema([
                        Forms\Components\TextInput::make('load_capacity')
                            ->label('Capacidad de carga (kg)')
                            ->nullable()
                            ->numeric(),
                        Forms\Components\TextInput::make('height')
                            ->label('Altura (cm)')
                            ->nullable()
                            ->numeric(),
                        Forms\Components\TextInput::make('width')
                            ->label('Ancho (cm)')
                            ->nullable()
                            ->numeric(),
                        Forms\Components\TextInput::make('depth')
                            ->label('Profundidad (cm)')
                            ->nullable()
                            ->numeric(),
                        Forms\Components\TextInput::make('weight')
                            ->label('Peso (kg)')
                            ->nullable()
                            ->numeric(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Especificaciones Técnicas')
                    ->description('Detalles técnicos de la lavadora')
                    ->icon('heroicon-o-cog')
                    ->schema([
                        Forms\Components\TextInput::make('motor_power')
                            ->label('Potencia del motor (W)')
                            ->nullable()
                            ->numeric(),
                        Forms\Components\TextInput::make('spin_speed')
                            ->label('Velocidad de centrifugado (RPM)')
                            ->nullable()
                            ->numeric(),
                        Forms\Components\TextInput::make('energy_consumption')
                            ->label('Consumo energético (calificación)')
                            ->nullable(),
                        Forms\Components\Select::make('motor_type')
                            ->label('Tipo de motor')
                            ->options([
                                'Inverter' => 'Inverter',
                                'Tradicional' => 'Tradicional',
                            ])
                            ->nullable(),
                        Forms\Components\TextInput::make('washing_program_count')
                            ->label('Cantidad de programas de lavado')
                            ->nullable()
                            ->numeric(),
                        Forms\Components\Repeater::make('available_temperatures')
                            ->label('Temperaturas disponibles')
                            ->schema([
                                Forms\Components\TextInput::make('temperature')
                                    ->label('Temperatura')
                                    ->placeholder('Ejemplo: 30°C, 40°C'),
                            ])
                            ->nullable(),
                        Forms\Components\TextInput::make('noise_level')
                            ->label('Nivel de ruido (decibelios)')
                            ->nullable()
                            ->numeric(),
                        Forms\Components\TextInput::make('water_efficiency')
                            ->label('Eficiencia de agua (litros por ciclo)')
                            ->nullable()
                            ->numeric(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Estatus')
                    ->description('Estado actual de la lavadora')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
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
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        $tenant = Filament::getTenant();
        return $table
            ->columns([
                //
                // En celular esta columna carga sola con el estatus y el cliente
                // como subtítulo, para que la fila quepa con sus acciones.
                Tables\Columns\TextColumn::make('machine_code')
                    ->label('Código')
                    // En celular el subtítulo carga qué es, cómo está y con quién.
                    ->description(fn (WashingMachine $record): string => collect([
                        $record->kindLabel(),
                        ucfirst(str_replace('_', ' ', (string) $record->status)),
                        $record->activeRental?->customer?->name,
                    ])->filter()->join(' · '))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kind')
                    ->label('Qué es')
                    ->badge()
                    ->visibleFrom('md')
                    ->formatStateUsing(fn (?string $state) => WashingMachine::KINDS[$state] ?? 'Lavadora')
                    ->color(fn (?string $state): string => match ($state) {
                        'secadora' => 'warning',
                        'combo' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('brand')
                    ->visibleFrom('md')
                    ->label('Marca')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('model')
                    ->visibleFrom('md')
                    ->label('Modelo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->visibleFrom('md')
                    ->formatStateUsing(fn (?string $state) => $state
                        ? ucfirst(str_replace('_', ' ', $state))
                        : '—')
                    ->searchable()
                    ->sortable()
                    ->color(fn(string $state): string => match ($state) {
                        'disponible' => 'primary',
                        'rentada' => 'success',
                        'mantenimiento' => 'gray',
                        'vendida' => 'info',
                        'fuera_de_servicio' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('activeRental.status')
                    ->visibleFrom('md')
                    ->label('Estatus Renta')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '—')
                    ->searchable()
                    ->sortable()
                    ->color(fn(string $state): string => match ($state) {
                        'activa' => 'primary',
                        'vencida' => 'danger',
                        'completada' => 'info',
                        'cancelada' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('activeRental.customer.name')
                    ->label('Cliente')
                    ->visibleFrom('md')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('activeRental.start_date')
                    ->visibleFrom('md')
                    ->label('Fecha de Inicio')
                    ->date('d/m/Y')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('activeRental.end_date')
                    ->visibleFrom('md')
                    ->label('Fecha de Fin')
                    ->date('d/m/Y')
                    ->searchable()
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('import')
                    ->label('Importar')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('Archivo Excel (.xlsx)')
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $file = storage_path('app/public/' . $data['file']);
                        Excel::import(new WashingMachinesImport(Filament::getTenant()->id), $file);
                        Notification::make()
                            ->title('Lavadoras importadas correctamente')
                            ->success()
                            ->send();
                    }),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->options(WashingMachine::KINDS)
                    ->label('Qué es'),
                SelectFilter::make('status')
                    ->options([
                        'disponible' => 'Disponible',
                        'rentada' => 'Rentada',
                        'mantenimiento' => 'Mantenimiento',
                        'vendida' => 'Vendida',
                        'fuera_de_servicio' => 'Fuera de Servicio',
                    ])
                    ->label('Estatus'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                // Editar y Restaurar se van al menú: sueltas empujaban la fila
                // fuera de la pantalla en celular.
                Tables\Actions\Action::make('qr_code')
                    ->label('QR')
                    ->iconButton()
                    ->tooltip('Descargar QR')
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->url(fn ($record) => route('qr.download', $record))
                    ->openUrlInNewTab(),
                ActionGroup::make([
                    \App\Filament\Resources\RentalResource\Actions\AbonarAction::make(
                        $tenant,
                        fn (WashingMachine $record) => $record->activeRental,
                    ),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\RestoreAction::make(),
                    Actions\RentAction::make($tenant),
                    Actions\ExtendRentAction::make($tenant),

                    // "en_mantenimiento" no existe en el enum: el valor es
                    // "mantenimiento". Esa mitad de la condición nunca se cumplía.
                    Tables\Actions\Action::make('make_available')
                        ->visible(fn(WashingMachine $record) => in_array($record->status, ['rentada', 'mantenimiento']) && in_array($record->activeRental?->status, ['activa', 'vencida']))
                        ->label('Cancelar Renta')
                        ->color('danger')
                        ->icon('heroicon-s-check-circle')
                        ->requiresConfirmation()
                        ->action(function (array $data, WashingMachine $record) use ($tenant) {
                            $record->update(['status' => 'disponible']);
                            $record->activeRental->update(['status' => 'cancelada', 'end_date' => new Carbon()]);
                            Notification::make()
                                ->title('La lavadora esta disponible y la renta ha sido cancelada')
                                ->success()
                                ->send();
                        }),
                    // Antes solo aparecía con la renta vencida: si el cliente
                    // estaba al corriente y devolvía la lavadora, la única
                    // salida era cancelar, y esa renta sí se cumplió.
                    Tables\Actions\Action::make('pick_up')
                        ->visible(fn(WashingMachine $record) => in_array($record->status, ['rentada', 'mantenimiento']) && in_array($record->activeRental?->status, ['activa', 'vencida']))
                        ->label('Recoger equipo')
                        ->icon('heroicon-s-check-circle')
                        ->color('success')
                        ->modalHeading('Recoger el equipo')
                        ->modalDescription('La renta queda como completada y el equipo vuelve a estar disponible.')
                        ->modalSubmitActionLabel('Recoger')
                        // Al recoger es cuando toca devolver el depósito, y es
                        // justo cuando se olvida: el formulario lo pone enfrente.
                        ->form(fn (WashingMachine $record) => $record->activeRental?->hasPendingDeposit()
                            ? [
                                Forms\Components\Placeholder::make('deposito_dejado')
                                    ->label('Dejó de depósito')
                                    ->content('$' . number_format($record->activeRental->deposit, 2)),
                                Forms\Components\TextInput::make('deposit_returned')
                                    ->label('Cuánto le devuelves')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step('0.01')
                                    ->prefix('$')
                                    ->default(fn () => $record->activeRental->deposit)
                                    ->required()
                                    ->helperText('Si le descuentas algo por daños, ponlo aquí y anótalo en las notas.'),
                                Forms\Components\Textarea::make('deposit_notes')
                                    ->label('Notas de la devolución')
                                    ->rows(2),
                            ]
                            : []
                        )
                        ->action(function (array $data, WashingMachine $record) use ($tenant) {
                            $renta = $record->activeRental;

                            $record->update(['status' => 'disponible']);

                            $cambios = ['status' => 'completada', 'end_date' => now()->toDateString()];

                            if ($renta?->hasPendingDeposit() && isset($data['deposit_returned'])) {
                                $cambios['deposit_returned'] = (float) $data['deposit_returned'];
                                $cambios['deposit_returned_at'] = now();

                                if (filled($data['deposit_notes'] ?? null)) {
                                    $cambios['notes'] = trim(($renta->notes ? $renta->notes . ' · ' : '')
                                        . 'Depósito: ' . $data['deposit_notes']);
                                }
                            }

                            $record->rentals()
                                ->whereIn('status', ['activa', 'vencida'])
                                ->update($cambios);

                            $retenido = isset($cambios['deposit_returned'])
                                ? (float) $renta->deposit - $cambios['deposit_returned']
                                : 0.0;

                            Notification::make()
                                ->title($record->kindLabel() . ' recogida y disponible')
                                ->body(match (true) {
                                    ! isset($cambios['deposit_returned']) => null,
                                    $retenido > 0 => 'Le devolviste $' . number_format($cambios['deposit_returned'], 2)
                                        . ' y retuviste $' . number_format($retenido, 2) . '.',
                                    default => 'Depósito devuelto completo.',
                                })
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('make_maintenance')
                        ->visible(fn(WashingMachine $record) => !in_array($record->status, ['mantenimiento']))
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
                            if ($data['start_date'] === Carbon::now()->format('Y-m-d')) {
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
                        ->visible(fn(WashingMachine $record) => in_array($record->status, ['mantenimiento']))
                        ->label('Terminar Mantenimiento')
                        ->icon('heroicon-s-wrench-screwdriver')
                        ->requiresConfirmation()
                        ->action(function (array $data, WashingMachine $record) use ($tenant) {

                            $maintenance = $record->maintenances()->whereIn('status', ['en_progreso', 'programada'])->first();
                            $maintenance->completeMaintenance();
                            $rental = $record->rentals()->where('status', 'activa')->first();
                            if ($rental) {
                                $days = $maintenance->getDurationInDays();
                                if ($days > 0) {
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
        return [];
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
