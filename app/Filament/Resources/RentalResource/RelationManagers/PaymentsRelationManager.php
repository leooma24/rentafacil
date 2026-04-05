<?php

namespace App\Filament\Resources\RentalResource\RelationManagers;

use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';
    protected static ?string $title = 'Pagos';
    protected static ?string $modelLabel = 'Pago';

    public function form(Form $form): Form
    {
        $tenant = Filament::getTenant();

        return $form
            ->schema([
                Forms\Components\DatePicker::make('payment_date')
                    ->label('Fecha de Pago')
                    ->default(now())
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->label('Monto')
                    ->numeric()
                    ->default($tenant?->settings?->price)
                    ->prefix('$')
                    ->required(),
                Forms\Components\Select::make('payment_method')
                    ->label('Método de Pago')
                    ->options([
                        'Efectivo' => 'Efectivo',
                        'Tarjeta de Crédito' => 'Tarjeta de Crédito',
                        'Tarjeta de Débito' => 'Tarjeta de Débito',
                        'Transferencia Bancaria' => 'Transferencia Bancaria',
                        'PayPal' => 'PayPal',
                    ])
                    ->default('Efectivo')
                    ->required(),
                Forms\Components\TextInput::make('reference')
                    ->label('Referencia'),
                Forms\Components\Select::make('status')
                    ->label('Estado')
                    ->options([
                        'completado' => 'Completado',
                        'pendiente' => 'Pendiente',
                        'fallido' => 'Fallido',
                    ])
                    ->default('completado')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Fecha')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money('MXN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Método'),
                Tables\Columns\TextColumn::make('reference')
                    ->label('Referencia')
                    ->placeholder('N/A'),
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
            ->defaultSort('payment_date', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = Filament::getTenant()->id;
                        return $data;
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
            ]);
    }
}
