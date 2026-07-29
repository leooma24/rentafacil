<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Un aparato en renta: lavadora, secadora o combo.
 *
 * DEUDA DE NOMBRES: la clase y la tabla se llaman WashingMachine aunque hoy también
 * representan secadoras. No se renombró porque Filament Shield guarda los permisos
 * como cadenas (view_any_washing::machine, update_washing::machine, …) y esas cadenas
 * ya están asignadas a los roles de 43 usuarios en producción; renombrar obliga a
 * migrarlas. Si algún día se hace, que sea en un cambio dedicado y no de pasada.
 *
 * De cara al dueño la pantalla dice "Equipos", que es lo que importa.
 */
class WashingMachine extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    /** Qué clase de aparato es. La columna type describe otra cosa: cómo carga. */
    public const KINDS = [
        'lavadora' => 'Lavadora',
        'secadora' => 'Secadora',
        'combo' => 'Lavadora-secadora',
    ];

    /** El plural va escrito y no armado con una "s": "lavadora-secadoras" no existe. */
    public const KINDS_PLURAL = [
        'lavadora' => 'lavadoras',
        'secadora' => 'secadoras',
        'combo' => 'combos',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'machine_code', 'brand', 'model', 'kind'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Equipo {$eventName}");
    }

    protected $fillable = [
        'machine_code',
        'brand',
        'model',
        'kind',
        'status',
        'serial_number',
        'purchase_date',
        'purchase_price',
        'type',
        'color',
        'load_capacity',
        'height',
        'width',
        'depth',
        'weight',
        'motor_power',
        'spin_speed',
        'energy_consumption',
        'motor_type',
        'washing_program_count',
        'available_temperatures',
        'noise_level',
        'water_efficiency',
    ];

    protected $casts = [
        'available_temperatures' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function rentals()
    {
        return $this->hasMany(Rental::class);
    }

    public function activeRental()
    {
        // El filtro va DENTRO de la subconsulta: latestOfMany() arma un MAX(id) que
        // ignora el where externo, así que si la lavadora tuvo una renta finalizada
        // con id mayor, elegía esa y el filtro de afuera la descartaba, dejando la
        // relación en nulo.
        return $this->hasOne(Rental::class)->ofMany(
            ['id' => 'max'],
            fn ($query) => $query->whereIn('status', ['activa', 'vencida'])
        );
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'rentals')
            ->withPivot('start_date', 'end_date', 'status', 'notes')
            ->withTimestamps();
    }

    public function maintenances()
    {
        return $this->hasMany(Maintenance::class);
    }

    public function getNameAttribute()
    {
        return $this->machine_code . ' ' . $this->brand . ' ' . $this->model;
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? self::KINDS['lavadora'];
    }
}
