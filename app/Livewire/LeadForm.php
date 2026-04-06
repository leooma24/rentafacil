<?php

namespace App\Livewire;

use App\Models\ProspectiveClient;
use Livewire\Component;

class LeadForm extends Component
{
    public string $name = '';
    public string $phone = '';
    public string $email = '';
    public string $city = '';
    public ?int $num_washers = null;
    public bool $submitted = false;

    protected $rules = [
        'name' => 'required|string|max:255',
        'phone' => 'required|string|max:20',
        'email' => 'nullable|email|max:255',
        'city' => 'nullable|string|max:255',
        'num_washers' => 'nullable|integer|min:1',
    ];

    public function submit()
    {
        $this->validate();

        ProspectiveClient::create([
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'city' => $this->city,
            'num_washers' => $this->num_washers,
            'source' => 'landing',
            'status' => 'nuevo',
        ]);

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.lead-form');
    }
}
