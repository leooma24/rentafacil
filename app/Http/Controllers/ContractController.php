<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use Barryvdh\DomPDF\Facade\Pdf;

class ContractController extends Controller
{
    public function download(Rental $rental)
    {
        $rental->load(['customer', 'washingMachine', 'company']);

        $pdf = Pdf::loadView('pdf.rental-contract', [
            'rental' => $rental,
            'customer' => $rental->customer,
            'machine' => $rental->washingMachine,
            'company' => $rental->company,
            'settings' => $rental->company->settings,
        ]);

        $filename = "contrato-RNT-" . str_pad($rental->id, 6, '0', STR_PAD_LEFT) . ".pdf";

        return $pdf->download($filename);
    }
}
