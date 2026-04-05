<?php

namespace App\Imports;

use App\Models\WashingMachine;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class WashingMachinesImport implements ToModel, WithHeadingRow, WithValidation
{
    public function __construct(protected int $companyId)
    {
    }

    public function model(array $row)
    {
        return new WashingMachine([
            'company_id' => $this->companyId,
            'machine_code' => $row['codigo'],
            'brand' => $row['marca'],
            'model' => $row['modelo'] ?? null,
            'serial_number' => $row['numero_serie'] ?? null,
            'type' => $row['tipo'] ?? null,
            'status' => 'disponible',
        ]);
    }

    public function rules(): array
    {
        return [
            'codigo' => 'required|string|max:255',
            'marca' => 'required|string|max:255',
            'modelo' => 'nullable|string|max:255',
            'numero_serie' => 'nullable|string|max:255',
            'tipo' => 'nullable|string|max:255',
        ];
    }
}
