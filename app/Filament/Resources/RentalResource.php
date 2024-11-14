<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RentalResource\Pages;
use App\Filament\Resources\RentalResource\RelationManagers;
use App\Models\Rental;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Facades\Filament;

class RentalResource extends Resource
{
    protected static ?string $model = Rental::class;

    protected static ?string $navigationIcon = 'heroicon-s-currency-dollar';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Renta';
    protected static ?string $pluralModelLabel = 'Rentas';
    protected static ?string $navigationLabel = 'Mis Rentas';
    protected static ?string $slug = 'mis-rentas';

    public static function form(Form $form): Form
    {
        $tenant = Filament::getTenant();
        return $form
            ->schema([
                //
                Forms\Components\Select::make('customer_id')
                    ->label('Cliente')
                    ->options(
                        $tenant->customers()->pluck('name', 'id')
                    )
                    ->required(),
                Forms\Components\Select::make('washing_machine_id')
                    ->label('Lavadora')
                    ->options(
                        $tenant->washingMachines()->where('status', 'disponible')->pluck('machine_code', 'id')
                    )
                    ->required(),
                Forms\Components\DatePicker::make('start_date')
                    ->label('Fecha de Inicio')
                    ->default(now())
                    ->native(false)
                    ->format('Y-m-d')
                    ->required(),
                Forms\Components\DatePicker::make('end_date')
                    ->label('Fecha de Fin')
                    ->native(false)
                    ->format('Y-m-d'),
                Forms\Components\Select::make('status')
                    ->label('Estatus')
                    ->options([
                        'activa' => 'Activa',
                        'vencida' => 'Vencida',
                        'completa' => 'Completa',
                        'cancelada' => 'Cancelada',
                    ])
                    ->default('activa')
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->label('Notas')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('washingMachine.machine_code')
                    ->label('Lavadora')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Fecha de Inicio')
                    ->date()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fecha de Fin')
                    ->date()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->searchable()
                    ->sortable(),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListRentals::route('/'),
            'create' => Pages\CreateRental::route('/create'),
            'edit' => Pages\EditRental::route('/{record}/edit'),
        ];
    }
}
