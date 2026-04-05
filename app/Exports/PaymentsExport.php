<?php

namespace App\Exports;

use App\Models\Payment;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PaymentsExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(protected int $companyId)
    {
    }

    public function query()
    {
        return Payment::query()
            ->where('company_id', $this->companyId)
            ->with(['rental.customer', 'rental.washingMachine']);
    }

    public function headings(): array
    {
        return ['ID', 'Cliente', 'Lavadora', 'Monto', 'Fecha Pago', 'Método', 'Referencia', 'Estado'];
    }

    public function map($payment): array
    {
        return [
            $payment->id,
            $payment->rental?->customer?->name,
            $payment->rental?->washingMachine?->machine_code,
            $payment->amount,
            $payment->payment_date,
            $payment->payment_method,
            $payment->reference,
            $payment->status,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
