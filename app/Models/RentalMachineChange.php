<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un cambio de equipo dentro de la misma renta.
 *
 * Guarda de dónde a dónde y por qué, que es lo que se pierde si sólo se
 * reemplaza washing_machine_id.
 */
class RentalMachineChange extends Model
{
    public const REASONS = [
        'falla' => 'Se descompuso',
        'mantenimiento' => 'Entró a mantenimiento',
        'peticion' => 'Lo pidió el cliente',
        'mejora' => 'Se le cambió por uno mejor',
        'otro' => 'Otro',
    ];

    protected $fillable = [
        'rental_id',
        'from_machine_id',
        'to_machine_id',
        'changed_by',
        'reason',
        'notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (RentalMachineChange $cambio) {
            $cambio->changed_by ??= auth()->id();
        });
    }

    public function rental()
    {
        return $this->belongsTo(Rental::class);
    }

    public function fromMachine()
    {
        return $this->belongsTo(WashingMachine::class, 'from_machine_id');
    }

    public function toMachine()
    {
        return $this->belongsTo(WashingMachine::class, 'to_machine_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
