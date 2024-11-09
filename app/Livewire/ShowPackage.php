<?php

namespace App\Livewire;

use Livewire\Component;

class ShowPackage extends Component
{
    public function render()
    {
        // get id from url
        $id = request()->route('package');
        $package = \App\Models\Package::find($id);
        return view('livewire.show-package', compact('package'));
    }
}
