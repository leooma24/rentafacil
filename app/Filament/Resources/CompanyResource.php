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
use Filament\Forms\Components\Section;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
            Section::make('Usuarios')
                ->columns(3)
                ->schema(
                    [
                        Forms\Components\Select::make('members')
                            ->relationship('members', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                    ]
                ),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('members.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
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
