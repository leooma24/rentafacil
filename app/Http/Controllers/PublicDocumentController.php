<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use App\Support\AccountStatement;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Lo que el cliente abre desde WhatsApp. Sin login: la liga viene firmada y el
 * middleware `signed` es quien valida que nadie la haya alterado.
 */
class PublicDocumentController extends Controller
{
    public function receipt(Payment $payment)
    {
        $payment->load(['rental.customer', 'rental.washingMachine', 'company']);

        return view('publico.recibo', [
            'payment' => $payment,
            'rental' => $payment->rental,
            'customer' => $payment->rental?->customer,
            'machine' => $payment->rental?->washingMachine,
            'company' => $payment->company,
        ]);
    }

    public function receiptPdf(Payment $payment)
    {
        $payment->load(['rental.customer', 'rental.washingMachine', 'company']);

        $pdf = Pdf::loadView('pdf.payment-receipt', [
            'payment' => $payment,
            'rental' => $payment->rental,
            'customer' => $payment->rental?->customer,
            'machine' => $payment->rental?->washingMachine,
            'company' => $payment->company,
        ]);

        return $pdf->download(
            'recibo-PAG-' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT) . '.pdf'
        );
    }

    public function statement(Customer $customer)
    {
        $customer->load('company');

        return view('publico.estado-de-cuenta', [
            'customer' => $customer,
            'company' => $customer->company,
            'statement' => app(AccountStatement::class)->forCustomer($customer),
        ]);
    }
}
