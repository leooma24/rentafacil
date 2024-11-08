<?php

namespace App\Livewire;

use App\Models\Package;
use Livewire\Component;

class ShowHome extends Component
{

    public function render()
    {
        $packages = Package::all();
        return view('livewire.show-home')
            ->layoutData(
                ['packages' => $packages]
            );
    }
}
