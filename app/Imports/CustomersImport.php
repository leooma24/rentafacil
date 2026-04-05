<?php

namespace App\Imports;

use App\Models\Customer;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class CustomersImport implements ToModel, WithHeadingRow, WithValidation
{
    public function __construct(protected int $companyId)
    {
    }

    public function model(array $row)
    {
        return new Customer([
            'company_id' => $this->companyId,
            'name' => $row['nombre'],
            'email' => $row['email'] ?? null,
            'phone' => $row['telefono'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:15',
        ];
    }
}
