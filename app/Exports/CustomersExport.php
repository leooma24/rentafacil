<?php

namespace App\Exports;

use App\Models\Customer;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomersExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(protected int $companyId)
    {
    }

    public function query()
    {
        return Customer::query()
            ->where('company_id', $this->companyId)
            ->with('rentals');
    }

    public function headings(): array
    {
        return ['ID', 'Nombre', 'Email', 'Teléfono', 'Rentas Activas', 'Creado'];
    }

    public function map($customer): array
    {
        return [
            $customer->id,
            $customer->name,
            $customer->email,
            $customer->phone,
            $customer->rentals->whereIn('status', ['activa', 'vencida'])->count(),
            $customer->created_at->format('d/m/Y'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
