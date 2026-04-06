<?php

namespace App\Livewire;

use Livewire\Component;

class CityLanding extends Component
{
    public string $slug;
    public string $city;
    public string $state;

    protected array $cities = [
        'culiacan' => ['city' => 'Culiacán', 'state' => 'Sinaloa'],
        'mazatlan' => ['city' => 'Mazatlán', 'state' => 'Sinaloa'],
        'los-mochis' => ['city' => 'Los Mochis', 'state' => 'Sinaloa'],
        'guasave' => ['city' => 'Guasave', 'state' => 'Sinaloa'],
        'hermosillo' => ['city' => 'Hermosillo', 'state' => 'Sonora'],
        'guadalajara' => ['city' => 'Guadalajara', 'state' => 'Jalisco'],
        'zapopan' => ['city' => 'Zapopan', 'state' => 'Jalisco'],
        'monterrey' => ['city' => 'Monterrey', 'state' => 'Nuevo León'],
        'tijuana' => ['city' => 'Tijuana', 'state' => 'Baja California'],
        'mexicali' => ['city' => 'Mexicali', 'state' => 'Baja California'],
        'cdmx' => ['city' => 'Ciudad de México', 'state' => 'CDMX'],
        'puebla' => ['city' => 'Puebla', 'state' => 'Puebla'],
        'queretaro' => ['city' => 'Querétaro', 'state' => 'Querétaro'],
        'leon' => ['city' => 'León', 'state' => 'Guanajuato'],
        'aguascalientes' => ['city' => 'Aguascalientes', 'state' => 'Aguascalientes'],
        'merida' => ['city' => 'Mérida', 'state' => 'Yucatán'],
        'cancun' => ['city' => 'Cancún', 'state' => 'Quintana Roo'],
        'chihuahua' => ['city' => 'Chihuahua', 'state' => 'Chihuahua'],
        'durango' => ['city' => 'Durango', 'state' => 'Durango'],
        'puerto-vallarta' => ['city' => 'Puerto Vallarta', 'state' => 'Jalisco'],
    ];

    public function mount(string $city)
    {
        $this->slug = $city;
        $data = $this->cities[$city] ?? null;

        if (!$data) {
            abort(404);
        }

        $this->city = $data['city'];
        $this->state = $data['state'];
    }

    public function render()
    {
        return view('livewire.city-landing')
            ->title("Renta de Lavadoras en {$this->city}, {$this->state} | Software de Gestión - Renta Fácil");
    }
}
