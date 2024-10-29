<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Filament\Resources\CustomerResource\RelationManagers\WashingMachinesRelationManager;
use App\Models\Customer;
use App\Models\Township;
use App\Models\Neighborhood;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $modelLabel = 'Cliente';
    protected static ?string $pluralModelLabel = 'Clientes';
    protected static ?string $navigationLabel = 'Mis Clientes';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información del Cliente')
                    ->columns('3')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(15),
                    ]),
                Section::make('Dirección')
                    ->collapsible()
                    ->schema([
                        Repeater::make('address')
                            ->label('')
                            ->deletable(false)
                            ->reorderable(false)
                            ->relationship()
                            ->columns(3)
                            ->minItems(1)
                            ->maxItems(1)
                            ->schema([
                                Forms\Components\TextInput::make('street')
                                    ->label('Calle')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('number')
                                    ->label('Número exterior')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('interior_number')
                                    ->label('Número interior')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('postal_code')
                                    ->label('Código Postal')
                                    ->required()
                                    ->maxLength(5)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function( Set $set, Get $get) {
                                        $neighborhood = Neighborhood::query()
                                            ->where('codigo_postal', $get('postal_code'))
                                            ->first();
                                        if ($neighborhood) {
                                            $set('city', $neighborhood->ciudad);
                                            $set('state_id', $neighborhood->township->state->id);
                                            $set('township_id', $neighborhood->municipio_id);
                                        }
                                    }),
                                Forms\Components\TextInput::make('city')
                                    ->label('Ciudad')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\Select::make('country_id')
                                    ->relationship('country', 'nombre')
                                    ->label('País')
                                    ->default('1')
                                    ->required(),
                                Forms\Components\Select::make('state_id')
                                    ->relationship('state', 'nombre')
                                    ->label('Estado')
                                    ->preload()
                                    ->live()
                                    ->searchable()
                                    ->required(),
                                Forms\Components\Select::make('township_id')
                                    ->preload()
                                    ->live()
                                    ->options(fn( Get $get): Collection => Township::query()
                                        ->where('estado_id', $get('state_id'))
                                        ->pluck('nombre', 'id') )
                                    ->label('Municipio')
                                    ->searchable()
                                    ->required(),
                                Forms\Components\Select::make('neighborhood_id')
                                    ->preload()
                                    ->live()
                                    ->options(function( Get $get) {
                                        if($get('postal_code')) {
                                            $neighborhoods = Neighborhood::query()
                                                ->where('codigo_postal', $get('postal_code'))
                                                ->get();
                                            if($neighborhoods->count() > 0) {
                                                return $neighborhoods->pluck('nombre', 'id');
                                            }
                                        }
                                        return Neighborhood::query()
                                            ->where('municipio_id', $get('township_id'))
                                            ->pluck('nombre', 'id');
                                    })
                                    ->label('Colonia')
                                    ->searchable()
                                    ->required(),


                            ])
                    ])


            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última Actualización')
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
            WashingMachinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
