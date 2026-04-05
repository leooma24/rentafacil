<?php

namespace App\Filament\Client\Widgets;

use App\Models\Rental;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MyRentals extends BaseWidget
{
    protected static ?string $heading = 'Mis Rentas';
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $companyIds = auth()->user()->companies()->pluck('companies.id');

        return $table
            ->query(
                Rental::whereIn('company_id', $companyIds)
                    ->whereIn('status', ['activa', 'vencida'])
                    ->with(['washingMachine', 'customer'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente'),
                Tables\Columns\TextColumn::make('washingMachine.machine_code')
                    ->label('Lavadora'),
                Tables\Columns\TextColumn::make('washingMachine.brand')
                    ->label('Marca'),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Inicio')
                    ->date(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Vencimiento')
                    ->date()
                    ->badge()
                    ->color(fn ($record) => $record->status === 'vencida' ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'activa' => 'success',
                        'vencida' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->paginated(false);
    }
}
