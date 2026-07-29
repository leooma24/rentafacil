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
        return $form
            ->schema([
                //
                Forms\Components\Select::make('customer_id')
                    ->label('Cliente')
                    ->options(
                        $tenant->customers()->pluck('name', 'id')
                    )
                    ->required(),
                Forms\Components\Select::make('washing_machine_id')
                    ->label('Equipo')
                    ->options(function ($record) use ($tenant) {
                        $options = $tenant->washingMachines()
                            ->where('status', 'disponible');
                        if ($record) {
                            $options->orWhere('id', $record->washing_machine_id);
                        }
                        // Con secadoras en el parque, el puro código no basta para
                        // saber qué se está asignando.
                        return $options->get()
                            ->mapWithKeys(fn ($equipo) => [
                                $equipo->id => "{$equipo->machine_code} · {$equipo->kindLabel()}"
                                    . ($equipo->brand ? " {$equipo->brand}" : ''),
                            ]);
                    })
                    ->searchable()
                    ->required(),
                Forms\Components\DatePicker::make('start_date')
                    ->label('Fecha de Inicio')
                    ->default(now())
                    ->native(false)
                    ->format('Y-m-d')
                    ->required(),
                Forms\Components\DatePicker::make('end_date')
                    ->label('Fecha de Fin')
                    ->native(false)
                    ->format('Y-m-d')
                    ->afterOrEqual('start_date'),
                Forms\Components\Select::make('status')
                    ->label('Estatus')
                    ->options([
                        'activa' => 'Activa',
                        'vencida' => 'Vencida',
                        'completada' => 'Completada',
                        'cancelada' => 'Cancelada',
                    ])
                    ->default('activa')
                    ->hiddenOn(['edit'])
                    ->required(),
                // El precio vive en la renta y no sólo en la configuración de la
                // empresa: así se cobra distinto por equipo o por cliente, y
                // cambiarle el precio a la empresa no mueve lo ya rentado.
                Forms\Components\TextInput::make('price')
                    ->label('Precio de esta renta')
                    ->numeric()
                    ->minValue(0)
                    ->step('0.01')
                    ->prefix('$')
                    ->default(fn () => \App\Support\RentalTerms::for($tenant)->price)
                    ->helperText('Puedes cobrar distinto por equipo o por cliente. Vacío usa el precio de tus Preferencias.'),

                Forms\Components\TextInput::make('deposit')
                    ->label('Depósito en garantía')
                    ->numeric()
                    ->minValue(0)
                    ->step('0.01')
                    ->prefix('$')
                    ->default(0)
                    ->helperText('Lo que dejó el cliente y hay que devolverle al terminar.'),

                Forms\Components\Textarea::make('notes')
                    ->label('Notas')
                    ->columnSpanFull(),
            ]);
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
