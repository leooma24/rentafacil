<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Maintenance extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'washing_machine_id',
        'technician_name',
        'start_date',
        'end_date',
        'maintenance_type',
        'description',
        'cost',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function washingMachine()
    {
        return $this->belongsTo(WashingMachine::class);
    }

     /**
     * Calcula la duración del mantenimiento en días.
     */
    public function getDurationInDays(): int
    {
        if ($this->start_date && $this->end_date) {
            return $this->start_date->diffInDays($this->end_date);
        }

        return 0;
    }

    /**
     * Marca el mantenimiento como completado y establece la fecha de fin.
     */
    public function completeMaintenance()
    {
        $this->update([
            'end_date' => Carbon::now(),
            'status' => 'completado',
        ]);
    }
}
