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
    ];

    public function company() : BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function rentals()
    {
        return $this->hasMany(Rental::class);
    }

    public function customers() : BelongsToMany
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

}
