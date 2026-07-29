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
     * Saca al equipo del taller y lo regresa a circulación.
     *
     * Vive aquí y no dentro del botón "Terminar mantenimiento" porque hay dos
     * caminos para dar por terminada una orden —ese botón y el formulario de
     * edición— y sólo uno liberaba el aparato. El otro lo dejaba marcado como en
     * mantenimiento para siempre: sin orden abierta que lo explique, sin
     * aparecer para rentar y sin que nadie se enterara.
     *
     * Vuelve a "rentada" y no a "disponible" cuando el cliente sigue con ella:
     * el equipo se le prestó, se reparó y se le regresó; la renta nunca se
     * cerró.
     */
    public function devolverEquipoACirculacion(): void
    {
        $equipo = $this->washingMachine;

        if (! $equipo || $equipo->status !== 'mantenimiento') {
            return;
        }

        $equipo->update([
            'status' => $equipo->rentals()->where('status', 'activa')->exists()
                ? 'rentada'
                : 'disponible',
        ]);
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
