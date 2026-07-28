<?php
namespace App\Filament\Resources\Components\Forms;

use App\Services\Geocoder;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
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
                // Nadie sabe su latitud de memoria. Antes había que escribirla a
                // mano y por eso, con 71 direcciones capturadas en producción,
                // ninguna tenía ubicación y el planificador de rutas nunca sirvió.
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('ubicar')
                        ->label('Buscar esta dirección en el mapa')
                        ->icon('heroicon-m-map-pin')
                        ->color('primary')
                        ->action(function (Get $get, Set $set) {
                            $texto = self::comoTextoPlano($get);

                            if ($texto === '') {
                                Notification::make()
                                    ->title('Primero escribe la calle y el número')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $coordenadas = app(Geocoder::class)->buscar($texto);

                            if (! $coordenadas) {
                                Notification::make()
                                    ->title('No se encontró esa dirección')
                                    ->body('Revisa la calle y el código postal, o captura la ubicación a mano.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $set('latitude', $coordenadas['lat']);
                            $set('longitude', $coordenadas['lng']);

                            Notification::make()
                                ->title('Ubicación encontrada')
                                ->body('Ya puedes incluir a este cliente en la ruta del día.')
                                ->success()
                                ->send();
                        }),
                ])->columnSpanFull(),

                Forms\Components\TextInput::make('latitude')
                    ->label('Latitud')
                    ->numeric()
                    ->placeholder('24.8049')
                    ->hint('Se llena sola con el botón de arriba'),
                Forms\Components\TextInput::make('longitude')
                    ->label('Longitud')
                    ->numeric()
                    ->placeholder('-107.3939')
                    ->hint('Sirve para armar la ruta de cobranza'),
            ]);
    }

    /**
     * La dirección que el dueño lleva escrita en el formulario, en una línea.
     *
     * Se arma de lo capturado y no del modelo guardado, para que el botón sirva
     * mientras se da de alta al cliente y no sólo al editarlo después.
     */
    private static function comoTextoPlano(Get $get): string
    {
        $calle = trim(($get('street') ?? '') . ' ' . ($get('number') ?? ''));

        if ($calle === '') {
            return '';
        }

        $colonia = $get('neighborhood_id')
            ? Neighborhood::find($get('neighborhood_id'))?->nombre
            : null;

        return implode(', ', array_filter([
            $calle,
            $colonia,
            $get('postal_code'),
            $get('city'),
            'México',
        ], fn ($parte) => filled($parte)));
    }
}
