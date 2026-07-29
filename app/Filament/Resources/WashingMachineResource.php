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
                Forms\Components\Section::make('Qué equipo es')
                    ->description('Con qué lo identificas cuando alguien te llame a decir que no enciende.')
                    ->icon('heroicon-o-cube')
                    ->iconColor('primary')
                    ->schema([
                        Forms\Components\Select::make('kind')
                            ->label('Qué es')
                            ->options(WashingMachine::KINDS)
                            ->default('lavadora')
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText('Lavadora, secadora o las dos en un mismo aparato.'),
                        Forms\Components\TextInput::make('machine_code')
                            ->label('Código')
                            ->required()
                            ->live(onBlur: true)
                            ->helperText('Como lo tienes rotulado en el aparato: LAV-001, SEC-002.'),
                        Forms\Components\TextInput::make('brand')
                            ->label('Marca')
                            ->live(onBlur: true),
                        Forms\Components\TextInput::make('model')
                            ->label('Modelo')
                            ->live(onBlur: true),
                        Forms\Components\TextInput::make('serial_number')
                            ->label('Número de serie')
                            ->nullable()
                            ->maxLength(255),
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

                        // El equipo, en una frase, mientras se captura. Ocho
                        // campos sueltos no dejan ver qué se está dando de alta.
                        Forms\Components\Placeholder::make('resumen')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->content(fn (Forms\Get $get) => new \Illuminate\Support\HtmlString(
                                self::resumenDelEquipo($get)
                            )),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Qué te costó')
                    ->description('De aquí sale cuánto lleva recuperado cada aparato en el reporte de rentabilidad.')
                    ->icon('heroicon-o-banknotes')
                    ->iconColor('success')
                    ->schema([
                        Forms\Components\TextInput::make('purchase_price')
                            ->label('Precio de compra')
                            ->nullable()
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->prefix('$')
                            ->suffix('MXN')
                            ->live(onBlur: true)
                            ->helperText('Sin esto, el reporte no puede decirte si este equipo ya se pagó solo.'),

                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Cuándo lo compraste')
                            ->nullable()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->format('Y-m-d')
                            ->maxDate(now()),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Cómo está')
                    ->description('Sólo los disponibles aparecen a la hora de armar una renta.')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->iconColor('warning')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Estatus')
                            ->options([
                                'disponible' => 'Disponible — se puede rentar',
                                'rentada' => 'Rentada — está con un cliente',
                                'en_revision' => 'En revisión — regresó y falta checarla',
                                'mantenimiento' => 'En mantenimiento',
                                'vendida' => 'Vendida',
                                'extraviada' => 'Extraviada',
                                'fuera_de_servicio' => 'Fuera de servicio',
                            ])
                            ->native(false)
                            ->default('disponible')
                            ->required()
                            ->helperText('Vendida, extraviada y fuera de servicio dejan de contar en tu ocupación.'),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Medidas y peso')
                    ->description('Para saber si cabe en la camioneta y si entra por la puerta del cliente.')
                    ->icon('heroicon-o-scale')
                    // Plegadas: casi nadie las llena, y abiertas hacían del alta
                    // de un equipo una pantalla de veinte campos.
                    ->collapsible()
                    ->collapsed()
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

                Forms\Components\Section::make('Ficha técnica')
                    ->description('Lo que trae la etiqueta del aparato. Opcional: sirve si algún día lo vendes o lo reclamas en garantía.')
                    ->icon('heroicon-o-cog')
                    ->collapsible()
                    ->collapsed()
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
            ]);
    }

    /**
     * Lo que quedó debiendo, y la decisión de qué hacer con eso.
     *
     * Va en el formulario de recoger porque es el último momento en que se puede
     * tomar: cerrar la renta mueve end_date, y a partir de ahí el adeudo ya no se
     * puede reconstruir. Antes se cerraba en silencio y el saldo se evaporaba.
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function preguntaDelAdeudo(?\App\Models\Rental $renta): array
    {
        if (! $renta) {
            return [];
        }

        $recoleccion = app(\App\Support\Recoleccion::class);
        $debia = $recoleccion->adeudo($renta);

        if ($debia <= 0) {
            return [
                Forms\Components\Placeholder::make('sin_adeudo')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->content(new \Illuminate\Support\HtmlString(
                        '<p class="rf-cfg-resumen">' . $recoleccion->resumen($renta) . '</p>'
                    )),
            ];
        }

        return [
            Forms\Components\Placeholder::make('adeudo_actual')
                ->hiddenLabel()
                ->columnSpanFull()
                ->content(new \Illuminate\Support\HtmlString(
                    '<p class="rf-cfg-resumen rf-cfg-resumen-falta">' . $recoleccion->resumen($renta) . '</p>'
                )),

            Forms\Components\Radio::make('adeudo')
                ->label('¿Y ese adeudo?')
                ->options([
                    'anotado' => 'Queda anotado — lo sigues viendo aunque ya no tenga equipo',
                    'perdonado' => 'Quedaron en paz — te llevaste la lavadora y ahí quedó',
                ])
                ->default('anotado')
                ->required()
                ->columnSpanFull(),
        ];
    }

    /** El aviso de qué pasó: el adeudo primero, que es lo que se olvidaba. */
    private static function avisoDeRecoleccion(
        WashingMachine $equipo,
        \App\Models\Rental $renta,
        array $datos,
        float $debia,
    ): void {
        $lineas = [];

        if ($debia > 0) {
            $lineas[] = ($datos['adeudo'] ?? 'anotado') === 'perdonado'
                ? 'Le perdonaste $' . number_format($debia, 2) . '.'
                : 'Quedó anotado que te debe $' . number_format($debia, 2) . '.';
        }

        if (isset($datos['deposit_returned']) && $renta->deposit > 0) {
            $retenido = (float) $renta->deposit - (float) $datos['deposit_returned'];
            $lineas[] = $retenido > 0
                ? 'Le devolviste $' . number_format((float) $datos['deposit_returned'], 2)
                    . ' de depósito y retuviste $' . number_format($retenido, 2) . '.'
                : 'Depósito devuelto completo.';
        }

        $lineas[] = 'Queda en revisión: márcala lista cuando la hayas checado.';

        Notification::make()
            ->title($equipo->kindLabel() . ' ' . $equipo->machine_code . ' recogida')
            ->body(implode(' ', $lineas))
            ->success()
            ->persistent()
            ->send();
    }

    /**
     * El equipo en una frase, mientras se captura.
     *
     * Con secadoras en el parque, "LAV-016" ya no dice qué se está dando de
     * alta, y el código se escribe a mano: un duplicado no se nota hasta que
     * dos aparatos distintos aparecen con el mismo nombre en la lista.
     */
    private static function resumenDelEquipo(Forms\Get $get): string
    {
        return self::resumenDe([
            'machine_code' => $get('machine_code'),
            'kind' => $get('kind'),
            'brand' => $get('brand'),
            'model' => $get('model'),
            'purchase_price' => $get('purchase_price'),
        ]);
    }

    /**
     * La frase en sí, a partir de valores sueltos.
     *
     * Separada de Forms\Get para poder comprobarla sin levantar medio Filament:
     * armar un Get de mentiras cuesta más que la propia función.
     *
     * @param array<string, mixed> $datos
     */
    public static function resumenDe(array $datos): string
    {
        $codigo = trim((string) ($datos['machine_code'] ?? ''));
        $tipo = WashingMachine::KINDS[$datos['kind'] ?? null] ?? 'Equipo';

        if ($codigo === '') {
            return '<p class="rf-cfg-resumen rf-cfg-resumen-falta">'
                . 'Ponle un <strong>código</strong>: es con lo que lo vas a buscar en la lista, '
                . 'en las rentas y en los reportes.</p>';
        }

        $marca = collect([$datos['brand'] ?? null, $datos['model'] ?? null])
            ->filter(fn ($valor) => filled($valor))
            ->join(' ');

        $precio = (float) ($datos['purchase_price'] ?? 0);

        $frase = '<strong>' . e($codigo) . '</strong> · ' . e(mb_strtolower($tipo));

        if ($marca !== '') {
            $frase .= ' ' . e($marca);
        }

        $frase .= '.';

        if ($precio > 0) {
            $frase .= ' Te costó <strong>$' . number_format($precio, 2) . '</strong>.';
        }

        return '<p class="rf-cfg-resumen">' . $frase . '</p>';
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
                        'en_revision' => 'warning',
                        'mantenimiento' => 'gray',
                        'vendida' => 'info',
                        'extraviada' => 'danger',
                        'fuera_de_servicio' => 'danger',
                        default => 'gray',
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
                        // Disco privado: el archivo trae la lista de clientes de
                        // la empresa, y storage/app/public se sirve tal cual en
                        // /storage/..., asi que ahi lo bajaria cualquiera.
                        Forms\Components\FileUpload::make('file')
                            ->label('Archivo Excel (.xlsx)')
                            ->disk('local')
                            ->directory('importaciones')
                            ->visibility('private')
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $file = \Illuminate\Support\Facades\Storage::disk('local')->path($data['file']);

                        Excel::import(new WashingMachinesImport(Filament::getTenant()->id), $file);
                        Notification::make()
                            ->title('Lavadoras importadas correctamente')
                            ->success()
                            ->send();

                        // El archivo trae la lista completa de la empresa: se
                        // borra en cuanto se leyo, no se queda guardado.
                        \Illuminate\Support\Facades\Storage::disk('local')->delete($data['file']);
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
                        'extraviada' => 'Extraviada',
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
                    \App\Filament\Resources\RentalResource\Actions\EntregarAction::make(
                        fn (WashingMachine $record) => $record->activeRental,
                    ),
                    \App\Filament\Resources\RentalResource\Actions\EntregarAction::acuse(
                        fn (WashingMachine $record) => $record->activeRental,
                    ),
                    \App\Filament\Resources\RentalResource\Actions\CambiarEquipoAction::make(
                        fn (WashingMachine $record) => $record->activeRental,
                    ),
                    Actions\MarcarExtraviadoAction::make(),
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
                    // El paso que faltaba entre recoger y volver a colocar. La
                    // lavadora regresa sucia o con algo roto, y sin este paso eso
                    // se descubria en la puerta del cliente siguiente.
                    Tables\Actions\Action::make('marcar_lista')
                        ->visible(fn (WashingMachine $record) => $record->status === 'en_revision')
                        ->label('Ya está lista')
                        ->icon('heroicon-o-sparkles')
                        ->color('success')
                        ->modalHeading('Dejarla lista para el siguiente cliente')
                        ->modalDescription('Confirma que ya la revisaste y la puedes volver a rentar.')
                        ->modalSubmitActionLabel('Está lista')
                        ->form([
                            Forms\Components\Textarea::make('notas')
                                ->label('¿Le hiciste algo?')
                                ->placeholder('Se lavó, se le cambió la manguera.')
                                ->rows(2)
                                ->helperText('Opcional. Si necesita reparación, mejor mándala a mantenimiento.'),
                        ])
                        ->action(function (array $data, WashingMachine $record) {
                            $record->update(['status' => 'disponible']);

                            Notification::make()
                                ->title($record->machine_code . ' lista para rentar')
                                ->body(filled($data['notas'] ?? null) ? $data['notas'] : null)
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('pick_up')
                        ->visible(fn(WashingMachine $record) => in_array($record->status, ['rentada', 'mantenimiento']) && in_array($record->activeRental?->status, ['activa', 'vencida']))
                        ->label('Recoger equipo')
                        ->icon('heroicon-s-check-circle')
                        ->color('success')
                        ->modalHeading('Recoger el equipo')
                        ->modalDescription('La renta se cierra y el equipo queda en revisión, para que nadie se lo lleve sin que lo hayas abierto.')
                        ->modalSubmitActionLabel('Recoger')
                        // Al recoger es cuando toca devolver el depósito, y es
                        // justo cuando se olvida: el formulario lo pone enfrente.
                        ->form(fn (WashingMachine $record) => array_merge(
                            // Lo que quedó debiendo se pone enfrente ANTES de
                            // cerrar: al recoger es cuando se decide si queda
                            // anotado o si quedaron en paz, y es lo único que
                            // después ya no se puede reconstruir.
                            self::preguntaDelAdeudo($record->activeRental),
                            [
                            // Las fotos de recolección son las que le dan sentido a
                            // las de entrega: sin el después, el antes no compara
                            // contra nada.
                            Forms\Components\FileUpload::make('pickup_photos')
                                ->label('Fotos de cómo lo devolvieron')
                                ->disk('privado')
                                ->image()
                                ->multiple()
                                ->maxFiles(6)
                                ->directory('recolecciones')
                                ->helperText($record->activeRental?->isDelivered()
                                    ? 'Compáralas con las de la entrega antes de devolver el depósito.'
                                    : 'De esta entrega no hay fotos previas para comparar.')
                                ->columnSpanFull(),
                            ], $record->activeRental?->hasPendingDeposit()
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
                        ))
                        ->action(function (array $data, WashingMachine $record) {
                            $renta = $record->activeRental;

                            if (! $renta) {
                                return;
                            }

                            $debia = app(\App\Support\Recoleccion::class)->ejecutar(
                                $renta,
                                quedaronEnPaz: ($data['adeudo'] ?? 'anotado') === 'perdonado',
                                extra: $data,
                            );

                            self::avisoDeRecoleccion($record, $renta, $data, $debia);
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
