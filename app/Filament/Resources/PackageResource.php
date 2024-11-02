<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PackageResource\Pages;
use App\Models\Package;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PackageResource extends Resource
{
    protected static ?string $model = Package::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Administrador';
    protected static ?string $modelLabel = 'Paquete';
    protected static ?string $pluralModelLabel = 'Paquetes';
    protected static ?string $navigationLabel = 'Paquetes';
    protected static ?string $slug = 'paquetes';
    protected static bool $isScopedToTenant = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('max_clients')
                    ->label('Máximo de clientes')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('max_washers')
                    ->label('Máximo de lavadoras')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('price')
                    ->label('Precio Mensual')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('max_clients')
                    ->label('Máximo de clientes')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_washers')
                    ->label('Máximo de lavadoras')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Precio Mensual')
                    ->money('MXN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListPackages::route('/'),
            'create' => Pages\CreatePackage::route('/create'),
            'edit' => Pages\EditPackage::route('/{record}/edit'),
        ];
    }

    public static function canAccess() : bool
    {
        return auth()->user()->hasRole('super_admin');
    }
}
