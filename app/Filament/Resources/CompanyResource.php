<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Filament\Resources\CompanyResource\RelationManagers;
use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\CompanyResource\Forms\CompanyForm;
use App\Filament\Resources\Components\Forms\AddressForm;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon = 'heroicon-s-building-office-2';

    protected static ?string $navigationGroup = 'Administrador';
    protected static ?string $modelLabel = 'Compañia';
    protected static ?string $pluralModelLabel = 'Compañias';
    protected static ?string $navigationLabel = 'Compañias';
    protected static bool $isScopedToTenant = false;

    public static function form(Form $form): Form
    {
        return $form
        ->schema([
            Section::make('Información de la Compañia')
                ->columns(3)
                ->schema(CompanyForm::getFormCompanyFields()
                ),
            Section::make('Dirección')
                ->collapsible()
                ->schema([AddressForm::getFormAddressFields()]),
            Section::make('Administración')
                ->columns(3)
                ->schema(
                    [
                        Forms\Components\Select::make('members')
                            ->label('Usuarios')
                            ->relationship('members', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                        Repeater::make('companyPackage')
                            ->label('Paquete')
                            ->relationship()
                            ->reorderable(false)
                            ->maxItems(1)
                            ->schema([
                                Forms\Components\Select::make('package_id')
                                    ->relationship('package', 'name'),
                                Forms\Components\DatePicker::make('start_date')
                                    ->date()
                                    ->native(false)
                                    ->displayFormat('Y-m-d')
                                    ->required(),
                                Forms\Components\DatePicker::make('end_date')
                                    ->date()
                                    ->native(false)
                                    ->displayFormat('Y-m-d')
                                    ->required(),
                            ]),
                    ]
                ),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable(),
                Tables\Columns\TextColumn::make('members.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('companyPackage.package.name')
                    ->label('Paquete')
                    ->searchable()
                    ->sortable()
                    ->badge(),
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
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
