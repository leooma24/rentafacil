<?php
namespace App\Filament\Resources\Components\Forms;

use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Models\Neighborhood;
use App\Models\Township;
use Illuminate\Support\Collection;

class AddressForm
{

    public static function getFormAddressFields(): Repeater
    {
        return Repeater::make('addresses')
            ->label('')
            ->deletable(false)
            ->reorderable(false)
            ->relationship()
            ->columns(3)
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

            ]);
    }
}
