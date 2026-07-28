<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\WashingMachineResource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestWashingMachines extends BaseWidget
{
    protected static ?int $sort = 5;
    protected static ?string $heading = 'Últimas Lavadoras';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                WashingMachineResource::getEloquentQuery(),
            )
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->defaultSort('created_at', 'desc')
            ->columns([
                // ...
                Tables\Columns\TextColumn::make('machine_code')
                    ->label('Código'),
                Tables\Columns\TextColumn::make('brand')
                    ->label('Marca'),
                Tables\Columns\TextColumn::make('model')
                    ->label('Modelo'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'disponible' => 'primary',
                        'rentada' => 'success',
                        'mantenimiento' => 'gray',
                        'vendida' => 'info',
                        'fuera_de_servicio' => 'danger',
                    }),
            ]);
    }
}
