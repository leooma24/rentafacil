<?php

namespace App\Exports;

use App\Models\Rental;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RentalsExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(protected int $companyId)
    {
    }

    public function query()
    {
        return Rental::query()
            ->where('company_id', $this->companyId)
            ->with(['customer', 'washingMachine']);
    }

    public function headings(): array
    {
        return ['ID', 'Cliente', 'Lavadora', 'Fecha Inicio', 'Fecha Fin', 'Estado', 'Notas', 'Creado'];
    }

    public function map($rental): array
    {
        return [
            $rental->id,
            $rental->customer?->name,
            $rental->washingMachine?->machine_code,
            $rental->start_date,
            $rental->end_date,
            $rental->status,
            $rental->notes,
            $rental->created_at->format('d/m/Y'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
