<?php

namespace App\Filament\Resources;

use App\Exports\RentalsExport;
use App\Filament\Resources\RentalResource\Pages;
use App\Filament\Resources\RentalResource\RelationManagers;
use App\Models\Rental;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use Maatwebsite\Excel\Facades\Excel;

class RentalResource extends Resource
{
    protected static ?string $model = Rental::class;

    protected static ?string $navigationIcon = 'heroicon-s-currency-dollar';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationGroup = 'Gestión Principal';
    protected static ?string $modelLabel = 'Renta';
    protected static ?string $pluralModelLabel = 'Rentas';
    protected static ?string $navigationLabel = 'Rentas';
    protected static ?string $slug = 'mis-rentas';

    public static function getNavigationBadge(): ?string
    {
        $tenant = Filament::getTenant();
        if (!$tenant) return null;
        $count = $tenant->rentals()->where('status', 'vencida')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Rentas vencidas';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['customer.name', 'washingMachine.machine_code'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return ($record->customer?->name ?? 'Cliente') . ' - ' . ($record->washingMachine?->machine_code ?? '');
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Estado' => ucfirst($record->status),
            'Vence' => $record->end_date ? \Carbon\Carbon::parse($record->end_date)->format('d/m/Y') : '-',
        ];
    }

    public static function form(Form $form): Form
    {
        $tenant = Filament::getTenant();
        $terms = \App\Support\RentalTerms::for($tenant);

        return $form
            ->schema([
                Forms\Components\Section::make('Quién y qué se lleva')
                    ->description('A qué cliente y cuál equipo. Sólo aparecen los que están disponibles.')
                    ->icon('heroicon-o-user-group')
                    ->iconColor('primary')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Cliente')
                            ->options($tenant->customers()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            // Darlo de alta sin salirse: si hay que ir a otra
                            // pantalla, se pierde lo capturado aquí.
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre')
                                    ->required(),
                                Forms\Components\TextInput::make('phone')
                                    ->label('Teléfono')
                                    ->tel(),
                                Forms\Components\TextInput::make('email')
                                    ->label('Correo')
                                    ->email(),
                            ])
                            ->createOptionUsing(fn (array $data) => $tenant->customers()->create($data)->id)
                            ->helperText('Si es nuevo, lo das de alta aquí mismo.'),

                        // Cómo se ha portado, ANTES de entregarle el aparato.
                        //
                        // Volverle a dar una lavadora a quien ya te falló es el
                        // error más caro del negocio —se pierde el aparato
                        // completo, no una semana de renta— y se cometía sin un
                        // solo aviso: la ficha del cliente sólo tenía nombre,
                        // correo y teléfono.
                        Forms\Components\Placeholder::make('historial')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->visible(fn (Forms\Get $get) => filled($get('customer_id')))
                            ->content(fn (Forms\Get $get) => new \Illuminate\Support\HtmlString(
                                self::comoSeHaPortado($get, $tenant)
                            )),

                        Forms\Components\Select::make('washing_machine_id')
                            ->label('Equipo')
                            ->options(function ($record) use ($tenant) {
                                $options = $tenant->washingMachines()
                                    ->where('status', 'disponible');

                                if ($record) {
                                    $options->orWhere('id', $record->washing_machine_id);
                                }

                                // Con secadoras en el parque, el puro código no
                                // basta para saber qué se está asignando.
                                return $options->orderBy('machine_code')->get()
                                    ->mapWithKeys(fn ($equipo) => [
                                        $equipo->id => "{$equipo->machine_code} · {$equipo->kindLabel()}"
                                            . ($equipo->brand ? " {$equipo->brand}" : ''),
                                    ]);
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->helperText('Al guardar queda marcado como rentado.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Cuánto y hasta cuándo')
                    ->description('El precio se precarga del de tus Preferencias; cámbialo si a este cliente le cobras distinto.')
                    ->icon('heroicon-o-banknotes')
                    ->iconColor('success')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('Precio de la renta')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->prefix('$')
                            ->suffix('MXN')
                            ->live(onBlur: true)
                            ->default(fn () => $terms->price)
                            ->helperText('Vacío usa el de tus Preferencias.'),

                        Forms\Components\TextInput::make('deposit')
                            ->label('Depósito en garantía')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->prefix('$')
                            ->suffix('MXN')
                            ->default(0)
                            ->live(onBlur: true)
                            ->helperText('Se lo devuelves al recoger el equipo.'),

                        Forms\Components\DatePicker::make('start_date')
                            ->label('Empieza')
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->format('Y-m-d')
                            ->required()
                            ->live(onBlur: true)
                            // La fecha de fin se recorre sola al mover el inicio:
                            // capturar las dos a mano es de donde salen las rentas
                            // que nacen vencidas.
                            ->afterStateUpdated(function ($state, Forms\Set $set) use ($terms) {
                                if ($state) {
                                    $set('end_date', $terms->endDateFrom($state)->toDateString());
                                }
                            }),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('Pagada hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->format('Y-m-d')
                            ->default(fn () => $terms->endDateFrom())
                            ->afterOrEqual('start_date')
                            ->live(onBlur: true)
                            ->helperText('Cada cobro la recorre ' . $terms->days . ' días.'),

                        Forms\Components\Select::make('status')
                            ->label('Estatus')
                            ->options([
                                'activa' => 'Activa',
                                'vencida' => 'Vencida',
                                'completada' => 'Completada',
                                'cancelada' => 'Cancelada',
                            ])
                            ->default('activa')
                            ->native(false)
                            ->hiddenOn(['create'])
                            ->columnSpanFull(),

                        // El trato, en una frase. Cinco campos sueltos no dejan
                        // ver si quedó como se acordó con el cliente.
                        Forms\Components\Placeholder::make('resumen')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->content(fn (Forms\Get $get) => new \Illuminate\Support\HtmlString(
                                self::resumenDelTrato($get, $tenant, $terms)
                            )),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Notas')
                    ->icon('heroicon-o-pencil-square')
                    ->collapsed(fn (?Rental $record) => blank($record?->notes))
                    ->collapsible()
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->hiddenLabel()
                            ->rows(3)
                            ->placeholder('Vive en la parte de atrás. Cobrar los sábados.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * El aviso de cómo se ha portado el cliente escogido.
     *
     * Va pegado al selector y no al pie del formulario a propósito: la decisión
     * de entregarle o no se toma en el momento de escogerlo, y un aviso hasta
     * abajo se lee cuando ya se decidió.
     */
    private static function comoSeHaPortado(Forms\Get $get, $tenant): string
    {
        $cliente = $tenant->customers()->find($get('customer_id'));

        if (! $cliente) {
            return '';
        }

        $historial = \App\Support\HistorialDelCliente::for($cliente);

        if (! $historial->hayQueAdvertir()) {
            // Del bueno también se dice algo: saber que alguien lleva dos años
            // pagando puntual es justo lo que permite no pedirle depósito.
            return '<p class="rf-cfg-resumen">' . e($historial->resumen()) . '</p>';
        }

        return '<p class="rf-cfg-resumen rf-cfg-resumen-falta">' . $historial->advertencia() . '</p>';
    }

    /** El trato en una frase, con lo que el dueño acaba de capturar. */
    private static function resumenDelTrato(
        Forms\Get $get,
        $tenant,
        \App\Support\RentalTerms $terms,
    ): string {
        $cliente = $get('customer_id')
            ? $tenant->customers()->whereKey($get('customer_id'))->value('name')
            : null;

        $equipo = $get('washing_machine_id')
            ? $tenant->washingMachines()->find($get('washing_machine_id'))
            : null;

        if (! $cliente || ! $equipo) {
            return '<div class="rf-cfg-resumen rf-cfg-resumen-neutro">'
                . 'Escoge cliente y equipo para ver cómo queda el trato.'
                . '</div>';
        }

        $frase = "<strong>{$cliente}</strong> se lleva {$equipo->machine_code} · "
            . mb_strtolower($equipo->kindLabel()) . '.';

        $precio = (float) ($get('price') ?: $terms->price ?: 0);

        if ($precio <= 0) {
            return '<div class="rf-cfg-resumen rf-cfg-resumen-falta">'
                . $frase . ' Falta ponerle precio: sin eso no se pueden registrar cobros.'
                . '</div>';
        }

        $frase .= ' Paga <strong>$' . number_format($precio, 2) . '</strong> cada '
            . $terms->days . ' días';

        $frase .= $get('end_date')
            ? ', cubierto hasta el ' . \Carbon\Carbon::parse($get('end_date'))->format('d/m/Y') . '.'
            : '.';

        $deposito = (float) ($get('deposit') ?: 0);

        if ($deposito > 0) {
            $frase .= ' Deja <strong>$' . number_format($deposito, 2)
                . '</strong> de depósito, que se le devuelve al entregar.';
        }

        return '<div class="rf-cfg-resumen">' . $frase . '</div>';
    }

    public static function table(Table $table): Table
    {
        $tenant = Filament::getTenant();

        return $table
            ->columns([
                //
                // En celular esta columna carga sola con la lavadora y el estatus
                // como subtítulo, para que la fila quepa y el botón de Cobrar
                // no se salga de la pantalla.
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->description(fn (Rental $record): string => collect([
                        $record->washingMachine?->machine_code,
                        ucfirst((string) $record->status),
                    ])->filter()->join(' · '))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('washingMachine.machine_code')
                    ->label('Lavadora')
                    ->visibleFrom('md')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Fecha de Inicio')
                    ->date('d/m/Y')
                    ->visibleFrom('md')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fecha de Fin')
                    ->date('d/m/Y')
                    ->visibleFrom('md')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->visibleFrom('md')
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '—')
                    ->searchable()
                    ->sortable(),

            ])
            ->headerActions([
                Tables\Actions\Action::make('export')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => Excel::download(
                        new RentalsExport(Filament::getTenant()->id),
                        'rentas-' . now()->format('Y-m-d') . '.xlsx'
                    )),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'activa' => 'Activa',
                        'vencida' => 'Vencida',
                        'completada' => 'Completada',
                        'cancelada' => 'Cancelada',
                    ]),
                Tables\Filters\Filter::make('end_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Vence desde'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Vence hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('end_date', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('end_date', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Desde ' . \Carbon\Carbon::parse($data['from'])->format('d/m/Y'));
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Hasta ' . \Carbon\Carbon::parse($data['until'])->format('d/m/Y'));
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                // Cobrar es la acción del negocio: va suelta y visible. El resto
                // se agrupa para que la fila no se salga de la pantalla.
                RentalResource\Actions\ExtendRentAction::make($tenant)
                    ->label('Cobrar')
                    ->button()
                    ->color('success'),
                Tables\Actions\ActionGroup::make([
                RentalResource\Actions\AbonarAction::make(
                    $tenant,
                    fn (Rental $record) => in_array($record->status, ['activa', 'vencida']) ? $record : null,
                ),
                // Entregar sale primero mientras falte; después queda el acuse.
                RentalResource\Actions\EntregarAction::make(fn (Rental $record) => $record),
                RentalResource\Actions\EntregarAction::acuse(fn (Rental $record) => $record),
                RentalResource\Actions\CambiarEquipoAction::make(fn (Rental $record) => $record),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('download_contract')
                    ->label('Contrato')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->url(fn ($record) => route('contract.download', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('send_whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->visible(fn ($record) => $record->customer?->phone && in_array($record->status, ['activa', 'vencida']))
                    ->requiresConfirmation()
                    ->modalHeading('Enviar recordatorio por WhatsApp')
                    ->modalDescription(fn ($record) => "Se enviará un mensaje a {$record->customer->name} ({$record->customer->phone})")
                    ->action(function ($record) {
                        $whatsapp = app(\App\Services\WhatsAppService::class);
                        $endDate = \Carbon\Carbon::parse($record->end_date)->format('d/m/Y');

                        if ($record->status === 'vencida') {
                            $whatsapp->sendOverdueNotice($record->customer->phone, $record->customer->name, $record->washingMachine->machine_code, now()->diffInDays($record->end_date));
                        } else {
                            $whatsapp->sendPaymentReminder($record->customer->phone, $record->customer->name, $record->washingMachine->machine_code, $endDate);
                        }

                        \Filament\Notifications\Notification::make()->title('Mensaje de WhatsApp enviado')->success()->send();
                    }),
                Tables\Actions\Action::make('send_payment_link')
                    ->label('Link de Pago')
                    ->icon('heroicon-o-link')
                    ->color('warning')
                    ->visible(fn ($record) => config('services.stripe.enabled') && in_array($record->status, ['activa', 'vencida']))
                    ->requiresConfirmation()
                    ->modalHeading('Generar link de pago')
                    ->modalDescription('Se generará un link de Stripe Checkout que puedes enviar al cliente.')
                    ->action(function ($record) {
                        $service = app(\App\Services\StripePaymentLinkService::class);
                        $url = $service->createPaymentLink($record);
                        if ($url) {
                            // Also send via WhatsApp if phone available
                            if ($record->customer?->phone) {
                                $whatsapp = app(\App\Services\WhatsAppService::class);
                                $endDate = \Carbon\Carbon::parse($record->end_date)->format('d/m/Y');
                                $whatsapp->sendPaymentReminder(
                                    $record->customer->phone,
                                    $record->customer->name,
                                    $record->washingMachine->machine_code,
                                    $endDate,
                                    $url,
                                );
                            }
                            \Filament\Notifications\Notification::make()
                                ->title('Link de pago generado')
                                ->body($url)
                                ->success()
                                ->persistent()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Error: configura el precio primero')
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('setup_recurring')
                    ->label('Pago Recurrente')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn ($record) => config('services.stripe.enabled') && $record->status === 'activa')
                    ->requiresConfirmation()
                    ->modalHeading('Activar pago recurrente con Stripe')
                    ->modalDescription('Se generará un link de Stripe para que el cliente configure su pago automático.')
                    ->action(function ($record) {
                        $service = app(\App\Services\StripeRecurringService::class);
                        $url = $service->createSubscriptionForRental($record);
                        if ($url) {
                            \Filament\Notifications\Notification::make()
                                ->title('Link de suscripción generado')
                                ->body($url)
                                ->success()
                                ->persistent()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Error: configura el precio en Preferencias primero')
                                ->danger()
                                ->send();
                        }
                    }),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRentals::route('/'),
            'create' => Pages\CreateRental::route('/create'),
            'edit' => Pages\EditRental::route('/{record}/edit'),
        ];
    }
}
