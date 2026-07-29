<?php

namespace App\Filament\Resources;

use App\Exports\PaymentsExport;
use App\Filament\Resources\PaymentResource\Pages;
use App\Filament\Resources\PaymentResource\RelationManagers;
use App\Models\Payment;
use App\Models\Rental;
use App\Support\Abonos;
use App\Support\RentalTerms;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationGroup = 'Finanzas';
    protected static ?string $modelLabel = 'Pago';
    protected static ?string $pluralModelLabel = 'Pagos';
    protected static ?string $navigationLabel = 'Pagos';
    protected static ?string $slug = 'pagos';

    public static function getNavigationBadge(): ?string
    {
        $tenant = Filament::getTenant();
        if (!$tenant) return null;
        $count = \App\Models\Payment::where('company_id', $tenant->id)->where('status', 'pendiente')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pagos pendientes';
    }

    public static function form(Form $form): Form
    {
        $tenant = Filament::getTenant();

        return $form
            ->schema([
                Forms\Components\Section::make('De quién es el pago')
                    ->description('Sólo salen las rentas abiertas. Para cobrar más rápido está el botón Cobrar en Rentas.')
                    ->icon('heroicon-o-user')
                    ->iconColor('primary')
                    ->schema([
                        // Antes era una caja donde había que teclear el número
                        // interno de la renta. Nadie se lo sabe de memoria, así
                        // que el pago se registraba en la renta equivocada o no
                        // se registraba.
                        Forms\Components\Select::make('rental_id')
                            ->label('Renta')
                            ->options(fn () => self::rentasParaCobrar($tenant))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                $renta = Rental::find($state);

                                if ($renta) {
                                    $set('amount', (float) RentalTerms::forRental($renta)->price);
                                }
                            })
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Cuánto y cómo')
                    ->icon('heroicon-o-banknotes')
                    ->iconColor('success')
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label('Cuánto te dio')
                            ->numeric()
                            ->minValue(0.01)
                            ->step('0.01')
                            ->prefix('$')
                            ->suffix('MXN')
                            ->required()
                            ->live(onBlur: true),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Cuándo')
                            ->default(today())
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->format('Y-m-d')
                            ->maxDate(today())
                            ->required(),

                        Forms\Components\Select::make('payment_method')
                            ->label('Cómo pagó')
                            ->options([
                                'Efectivo' => 'Efectivo',
                                'Transferencia Bancaria' => 'Transferencia bancaria',
                                'Tarjeta de Débito' => 'Tarjeta de débito',
                                'Tarjeta de Crédito' => 'Tarjeta de crédito',
                            ])
                            ->default('Efectivo')
                            ->native(false)
                            ->required()
                            // El corte de caja sólo cuadra el efectivo: lo demás
                            // ya está en el banco.
                            ->helperText('El efectivo es lo que tienes que cuadrar en tu corte.'),

                        Forms\Components\TextInput::make('reference')
                            ->label('Referencia')
                            ->maxLength(255)
                            ->helperText('Opcional: el folio de la transferencia, por ejemplo.'),

                        Forms\Components\Select::make('status')
                            ->label('Estatus')
                            ->options([
                                'completado' => 'Completado',
                                'pendiente' => 'Pendiente',
                                'cancelado' => 'Cancelado',
                            ])
                            ->default('completado')
                            ->native(false)
                            // Al registrar, un pago es un pago. El estatus sólo
                            // se toca después, si algo salió mal.
                            ->hiddenOn('create')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('resumen')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->content(fn (Forms\Get $get) => new \Illuminate\Support\HtmlString(
                                self::resumenDelCobro($get)
                            )),
                    ])
                    ->columns(2),
            ]);
    }

    /** Las rentas abiertas, rotuladas por cliente y no por número interno. */
    private static function rentasParaCobrar($tenant): array
    {
        if (! $tenant) {
            return [];
        }

        return Rental::where('company_id', $tenant->id)
            ->whereIn('status', ['activa', 'vencida'])
            ->with(['customer', 'washingMachine'])
            ->get()
            ->sortBy(fn (Rental $renta) => $renta->customer?->name)
            ->mapWithKeys(fn (Rental $renta) => [
                $renta->id => ($renta->customer?->name ?? 'Sin cliente')
                    . ' — ' . ($renta->washingMachine?->machine_code ?? 'sin equipo')
                    . ' · ' . ($renta->washingMachine?->kindLabel() ?? 'equipo'),
            ])
            ->all();
    }

    /**
     * Qué va a pasar con este pago, antes de guardarlo.
     *
     * Un cobro que completa el periodo recorre la fecha de la renta; uno más
     * chico queda como abono y no la mueve. Son dos cosas muy distintas y el
     * formulario no las distinguía por ningún lado.
     */
    private static function resumenDelCobro(Forms\Get $get): string
    {
        $renta = Rental::with('customer')->find($get('rental_id'));

        if (! $renta) {
            return '<p class="rf-cfg-resumen">Escoge la renta y te digo qué va a pasar con el pago.</p>';
        }

        $terms = RentalTerms::forRental($renta);
        $precio = (float) ($terms->price ?? 0);
        $monto = (float) $get('amount');

        if ($precio <= 0) {
            return '<p class="rf-cfg-resumen rf-cfg-resumen-falta">'
                . 'Esta renta va sin precio. Ponle uno en la renta o en tus Preferencias '
                . 'para poder saber cuánto cubre este pago.</p>';
        }

        $quien = e($renta->customer?->name ?? 'El cliente');
        $abonado = Abonos::creditFor($renta);
        $junta = $abonado + $monto;

        if ($monto <= 0) {
            return '<p class="rf-cfg-resumen">El periodo de <strong>' . $quien
                . '</strong> cuesta <strong>$' . number_format($precio, 2) . '</strong>.</p>';
        }

        if ($junta < $precio) {
            $faltan = $precio - $junta;

            return '<p class="rf-cfg-resumen">Queda como <strong>abono</strong>: no le recorre la fecha. '
                . $quien . ' llevaría <strong>$' . number_format($junta, 2) . '</strong> de $'
                . number_format($precio, 2) . ', le faltarían <strong>$' . number_format($faltan, 2)
                . '</strong>.</p>';
        }

        $periodos = (int) floor($junta / $precio);
        $sobra = $junta - $periodos * $precio;
        $nuevaFecha = $terms->endDateFrom($renta->end_date)->addDays($terms->days * ($periodos - 1));

        $frase = 'Le cubre <strong>' . $periodos . ' periodo' . ($periodos === 1 ? '' : 's')
            . '</strong> a ' . $quien . ': queda pagado hasta el <strong>'
            . $nuevaFecha->format('d/m/Y') . '</strong>.';

        if ($sobra > 0) {
            $frase .= ' Le sobran <strong>$' . number_format($sobra, 2) . '</strong> a favor.';
        }

        return '<p class="rf-cfg-resumen">' . $frase . '</p>';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('rental_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_date')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->searchable(),
                Tables\Columns\TextColumn::make('reference')
                    ->searchable(),
                Tables\Columns\TextColumn::make('applied')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Cobro' : 'Abono')
                    ->color(fn ($state) => $state ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'completado' => 'success',
                        'pendiente' => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => Excel::download(
                        new PaymentsExport(Filament::getTenant()->id),
                        'pagos-' . now()->format('Y-m-d') . '.xlsx'
                    )),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'completado' => 'Completado',
                        'pendiente' => 'Pendiente',
                        'fallido' => 'Fallido',
                    ]),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Método')
                    ->options([
                        'Efectivo' => 'Efectivo',
                        'Tarjeta de Crédito' => 'Tarjeta de Crédito',
                        'Tarjeta de Débito' => 'Tarjeta de Débito',
                        'Transferencia Bancaria' => 'Transferencia Bancaria',
                        'PayPal' => 'PayPal',
                    ]),
                Tables\Filters\Filter::make('payment_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('payment_date', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('payment_date', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Desde ' . Carbon::parse($data['from'])->format('d/m/Y'));
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Hasta ' . Carbon::parse($data['until'])->format('d/m/Y'));
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('download_receipt')
                    ->label('Recibo')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->url(fn ($record) => route('receipt.download', $record))
                    ->openUrlInNewTab(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
