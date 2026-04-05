<?php

namespace App\Http\Controllers;

use App\Models\Payment;
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

    public function receipt(Payment $payment)
    {
        $payment->load(['rental.customer', 'rental.washingMachine', 'company']);

        $pdf = Pdf::loadView('pdf.payment-receipt', [
            'payment' => $payment,
            'rental' => $payment->rental,
            'customer' => $payment->rental->customer,
            'machine' => $payment->rental->washingMachine,
            'company' => $payment->company,
        ]);

        $filename = "recibo-PAG-" . str_pad($payment->id, 6, '0', STR_PAD_LEFT) . ".pdf";

        return $pdf->download($filename);
    }
}
