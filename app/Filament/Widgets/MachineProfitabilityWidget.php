<?php

namespace App\Filament\Widgets;

use App\Models\WashingMachine;
use App\Support\RentabilidadDelEquipo;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Cuánto te ha dejado cada equipo, de verdad.
 *
 * Antes esta tabla tenía una sola columna de dinero —"Ingresos Totales"— que era
 * lo cobrado y nada más. Con eso, un aparato que cobró $8,000, costó $11,200 y
 * llevó $2,500 en reparaciones salía hasta arriba como si fuera el mejor del
 * parque, cuando va perdiendo $5,700. Y con ese número se decide qué marca volver
 * a comprar.
 */
class MachineProfitabilityWidget extends BaseWidget
{
    protected static ?int $sort = 9;

    // Sin esto el widget se queda en el placeholder de carga y nunca aparece.
    protected static bool $isLazy = false;

    protected static ?string $heading = 'Qué te ha dejado cada equipo';

    protected int | string | array $columnSpan = 'full';

    public function getTableRecordKey(\Illuminate\Database\Eloquent\Model $record): string
    {
        return (string) $record->getKey();
    }

    public function table(Table $table): Table
    {
        $tenant = Filament::getTenant();

        return $table
            ->description('Lo cobrado menos lo que costó y lo que llevas gastado en repararla.')
            ->query(
                WashingMachine::where('company_id', $tenant->id)
                    // Se calcula con subconsultas y no con withSum sobre un join:
                    // unir pagos y mantenimientos en la misma consulta multiplica
                    // las filas y las dos sumas salen infladas.
                    ->withCount('rentals')
                    ->addSelect([
                        'cobrado' => \App\Models\Payment::selectRaw('COALESCE(SUM(payments.amount), 0)')
                            ->join('rentals', 'rentals.id', '=', 'payments.rental_id')
                            ->whereColumn('rentals.washing_machine_id', 'washing_machines.id')
                            ->where('payments.status', 'completado'),
                        'gasto_mantenimiento' => \App\Models\Maintenance::selectRaw('COALESCE(SUM(cost), 0)')
                            ->whereColumn('maintenances.washing_machine_id', 'washing_machines.id'),
                    ])
            )
            ->defaultSort('cobrado', 'desc')
            ->columns([
                // En celular esta columna carga sola con el veredicto de subtítulo.
                Tables\Columns\TextColumn::make('machine_code')
                    ->label('Equipo')
                    ->description(fn (WashingMachine $record) => collect([
                        $record->kindLabel(),
                        trim($record->brand . ' ' . $record->model) ?: null,
                        RentabilidadDelEquipo::for($record)->veredicto(),
                    ])->filter()->join(' · '))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('rentals_count')
                    ->label('Rentas')
                    ->alignCenter()
                    ->sortable()
                    ->visibleFrom('lg'),

                Tables\Columns\TextColumn::make('cobrado')
                    ->label('Cobrado')
                    ->money('MXN')
                    ->sortable()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('purchase_price')
                    ->label('Costó')
                    ->money('MXN')
                    ->placeholder('—')
                    ->sortable()
                    ->visibleFrom('lg'),

                // La columna que faltaba. En este negocio el mantenimiento es el
                // gasto que decide: un aparato barato que se descompone cada dos
                // meses sale más caro que uno del doble que no se descompone.
                Tables\Columns\TextColumn::make('gasto_mantenimiento')
                    ->label('Reparaciones')
                    ->money('MXN')
                    ->color(fn ($state) => (float) $state > 0 ? 'warning' : 'gray')
                    ->sortable()
                    ->visibleFrom('lg'),

                Tables\Columns\TextColumn::make('resultado')
                    ->label('Te ha dejado')
                    ->state(fn (WashingMachine $record) => RentabilidadDelEquipo::for($record)->calculable()
                        ? RentabilidadDelEquipo::for($record)->resultado()
                        : null)
                    ->money('MXN')
                    ->placeholder('Falta su precio')
                    ->weight('bold')
                    ->color(fn (WashingMachine $record) => RentabilidadDelEquipo::for($record)->color()),
            ])
            ->defaultPaginationPageOption(5);
    }
}
