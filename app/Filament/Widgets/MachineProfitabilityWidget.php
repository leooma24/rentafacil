<?php

namespace App\Filament\Widgets;

use App\Models\WashingMachine;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class MachineProfitabilityWidget extends BaseWidget
{
    protected static ?int $sort = 9;
    // Sin esto el widget se queda en el placeholder de carga y nunca aparece.
    protected static bool $isLazy = false;
    protected static ?string $heading = 'Rentabilidad por Lavadora';
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $tenant = Filament::getTenant();

        return $table
            ->query(
                WashingMachine::where('company_id', $tenant->id)
                    ->withCount(['rentals'])
                    ->withSum(['rentals as total_revenue' => function (Builder $query) {
                        $query->join('payments', 'rentals.id', '=', 'payments.rental_id')
                            ->where('payments.status', 'completado');
                    }], 'payments.amount')
            )
            ->defaultSort('total_revenue', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('machine_code')
                    ->label('Código')
                    ->searchable(),
                Tables\Columns\TextColumn::make('brand')
                    ->label('Marca'),
                Tables\Columns\TextColumn::make('model')
                    ->label('Modelo'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'disponible' => 'primary',
                        'rentada' => 'success',
                        'mantenimiento' => 'gray',
                        'vendida' => 'info',
                        'fuera_de_servicio' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('rentals_count')
                    ->label('Total Rentas')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Ingresos Totales')
                    ->money('MXN')
                    ->sortable()
                    ->default(0),
            ])
            ->defaultPaginationPageOption(5);
    }
}
