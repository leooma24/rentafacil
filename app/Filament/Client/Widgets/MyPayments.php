<?php

namespace App\Filament\Client\Widgets;

use App\Models\Payment;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MyPayments extends BaseWidget
{
    protected static ?string $heading = 'Historial de Pagos';
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $companyIds = auth()->user()->companies()->pluck('companies.id');

        return $table
            ->query(
                Payment::whereIn('company_id', $companyIds)
                    ->with(['rental.washingMachine'])
                    ->latest('payment_date')
            )
            ->columns([
                Tables\Columns\TextColumn::make('rental.washingMachine.machine_code')
                    ->label('Lavadora'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money('MXN'),
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Fecha de Pago')
                    ->date(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Método'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completado' => 'success',
                        'pendiente' => 'warning',
                        'fallido' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultPaginationPageOption(10);
    }
}
