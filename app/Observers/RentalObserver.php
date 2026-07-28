<?php

namespace App\Observers;

use App\Mail\FirstRentalCongratsMail;
use App\Models\Rental;
use Illuminate\Support\Facades\Mail;

class RentalObserver
{
    public function created(Rental $rental): void
    {
        $rental->load(['company.members', 'washingMachine', 'customer']);

        // Las rentas sembradas para un demo no son un hito real del negocio.
        if ($rental->company?->is_demo) {
            return;
        }

        // Check if this is the company's first rental
        $totalRentals = Rental::where('company_id', $rental->company_id)->count();

        if ($totalRentals === 1) {
            $machineName = $rental->washingMachine?->machine_code . ' ' . $rental->washingMachine?->brand;
            $customerName = $rental->customer?->name ?? 'Cliente';

            foreach ($rental->company->members as $member) {
                try {
                    Mail::to($member->email)->send(new FirstRentalCongratsMail($member, $machineName, $customerName));
                } catch (\Exception $e) {
                    // Don't break flow
                }
            }
        }
    }
}
