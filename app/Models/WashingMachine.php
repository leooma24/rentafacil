<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WashingMachine extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_code',
        'brand',
        'model',
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

    public function rental()
    {
        return $this->hasOne(Rental::class)->whereIn('status', ['activa', 'vencida']);
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'rentals')
            ->withPivot('start_date', 'end_date', 'status', 'notes')
            ->withTimestamps();
    }

    public function getRentalAttribute()
    {
        return $this->rentals()->whereIn('status', ['activa', 'vencida'])->first();
    }

    public function maintenances()
    {
        return $this->hasMany(Maintenance::class);
    }

    public function getNameAttribute()
    {
        return $this->machine_code . ' ' . $this->brand . ' ' . $this->model;
    }
}
