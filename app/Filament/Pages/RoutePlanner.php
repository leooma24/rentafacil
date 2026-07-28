<?php

namespace App\Filament\Pages;

use App\Models\Rental;
use App\Services\RouteOptimizerService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class RoutePlanner extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationGroup = 'Gestión Principal';
    protected static ?string $title = 'Planificador de Rutas';
    protected static ?string $slug = 'rutas';
    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.route-planner';

    public ?array $data = [];
    public array $optimizedRoute = [];
    public ?string $mapsUrl = null;
    public ?float $totalDistance = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        $tenant = Filament::getTenant();

        // Get rentals with customer addresses that have coordinates
        $rentals = Rental::where('company_id', $tenant->id)
            ->whereIn('status', ['activa', 'vencida'])
            ->with(['customer.addresses', 'washingMachine'])
            ->get()
            ->filter(fn ($r) => $r->customer?->addresses?->first()?->hasCoordinates());

        $options = $rentals->mapWithKeys(function ($rental) {
            $addr = $rental->customer->addresses->first();
            $label = "{$rental->customer->name} — {$rental->washingMachine->machine_code} ({$addr->full_address})";
            if ($rental->status === 'vencida') {
                $label .= ' [VENCIDA]';
            }
            return [$rental->id => $label];
        })->toArray();

        return $form
            ->schema([
                Section::make('Selecciona las paradas')
                    ->description($options === []
                        ? 'Todavía no hay clientes ubicados en el mapa. Ve a Clientes, selecciona los que quieras y usa "Ubicar en el mapa".'
                        : 'Elige a quién vas a visitar y te armo la ruta más corta, lista para abrir en Google Maps.')
                    ->schema([
                        // Antes había que teclear la latitud de memoria. Ahora la
                        // pide el navegador y no pasa por ningún servidor ajeno.
                        Forms\Components\View::make('filament.forms.mi-ubicacion')
                            ->columnSpanFull(),
                        TextInput::make('origin_lat')
                            ->label('Latitud de partida')
                            ->numeric()
                            ->hint('Se llena con el botón de arriba')
                            ->placeholder('24.8049'),
                        TextInput::make('origin_lng')
                            ->label('Longitud de partida')
                            ->numeric()
                            ->hint('Opcional: sin esto la ruta arranca en la primera parada')
                            ->placeholder('-107.3939'),
                        CheckboxList::make('selected_rentals')
                            ->label('Visitas')
                            ->options($options)
                            ->columns(1)
                            ->required()
                            ->searchable(),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function calculateRoute(): void
    {
        $data = $this->form->getState();
        $rentalIds = $data['selected_rentals'] ?? [];

        if (count($rentalIds) < 2) {
            Notification::make()
                ->title('Selecciona al menos 2 paradas')
                ->warning()
                ->send();
            return;
        }

        $rentals = Rental::whereIn('id', $rentalIds)
            ->with(['customer.addresses', 'washingMachine'])
            ->get();

        $stops = $rentals->map(function ($rental) {
            $addr = $rental->customer->addresses->first();
            return [
                'id' => $rental->id,
                'name' => $rental->customer->name,
                'machine' => $rental->washingMachine->machine_code,
                'address' => $addr->full_address,
                'latitude' => (float) $addr->latitude,
                'longitude' => (float) $addr->longitude,
                'status' => $rental->status,
                'end_date' => $rental->end_date,
            ];
        })->toArray();

        $optimizer = new RouteOptimizerService();

        $origin = null;
        if (!empty($data['origin_lat']) && !empty($data['origin_lng'])) {
            $origin = [(float) $data['origin_lat'], (float) $data['origin_lng']];
        }

        $this->optimizedRoute = $optimizer->optimize($stops, $origin);
        $this->totalDistance = $optimizer->calculateTotalDistance($this->optimizedRoute);
        $this->mapsUrl = $optimizer->generateGoogleMapsUrl($this->optimizedRoute, $origin);

        Notification::make()
            ->title('Ruta optimizada con ' . count($this->optimizedRoute) . ' paradas')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
