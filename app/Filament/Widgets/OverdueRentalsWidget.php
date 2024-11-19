<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Filament\Resources\WashingMachineResource;

class OverdueRentalsWidget extends BaseWidget
{
    protected static ?int $sort = 5;
    protected static ?string $heading = 'Rentas Vencidas';
    public function table(Table $table): Table
    {
        return $table
            ->query(
                WashingMachineResource::getEloquentQuery()
                    ->whereHas(
                        'rentals',
                        fn($query) =>
                        $query->where('end_date', '<=', now())
                    )
            )
            ->defaultPaginationPageOption(5)
            ->paginated(false)
            ->defaultSort('created_at', 'desc')
            ->columns([
                // ...
                Tables\Columns\TextColumn::make('rental.customer.name')
                    ->label('Cliente'),
                Tables\Columns\TextColumn::make('machine_code')
                    ->label('Código'),
                Tables\Columns\TextColumn::make('rental.end_date')
                    ->label('Fecha de Vencimiento')
                    ->badge()
                    ->color('danger')
                    ->date(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'disponible' => 'primary',
                        'rentada' => 'success',
                        'mantenimiento' => 'gray',
                        'vendida' => 'info',
                        'fuera_de_servicio' => 'danger',
                    }),
            ]);
    }
}
