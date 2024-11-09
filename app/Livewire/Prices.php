<?php

namespace App\Livewire;

use Livewire\Component;

class Prices extends Component
{
    public $packages = [];

    public function mount($packages)
    {
        $this->packages = $packages;
    }

    public function render()
    {
        return view('livewire.prices');
    }
}
