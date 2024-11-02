<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Filament\Resources\CustomerResource;

class LatestCustomers extends BaseWidget
{
    protected static ?int $sort = 4;
    protected static ?string $heading = 'Últimos Clientes';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CustomerResource::getEloquentQuery(),
            )
            ->defaultPaginationPageOption(5)
            ->paginated(false)
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre'),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo Electrónico'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->date(),
            ]);
    }
}
