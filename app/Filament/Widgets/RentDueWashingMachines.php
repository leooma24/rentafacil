<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\RentalResource;
use App\Filament\Resources\RentalResource\Actions\ExtendRentAction;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use Filament\Widgets\TableWidget as BaseWidget;

class RentDueWashingMachines extends BaseWidget
{
    protected static ?int $sort = 6;
    protected static ?string $heading = 'Rentas por Vencer';
    //protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $tenant = Filament::getTenant();
        return $table
            ->query(
                RentalResource::getEloquentQuery()
                    ->where('end_date', '>=', Carbon::now())
                    ->where('end_date', '<=', Carbon::now()->addDays(3))
            )
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->defaultSort('end_date', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente'),
                Tables\Columns\TextColumn::make('washingMachine.machine_code')
                    ->label('Lavadora'),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fecha de vencimiento')
                    ->date()
                    ->badge(),
            ])->actions([
                ExtendRentAction::make($tenant),
            ]);
    }
}
