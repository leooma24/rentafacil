<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
    ];

    public function members()
    {
        return $this->belongsToMany(User::class);
    }

    public function washingMachines() : HasMany
    {
        return $this->hasMany(WashingMachine::class);
    }

    public function customers() : HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function rentals() : HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function maintenances() : HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    public function addresses()
    {
        return $this->morphMany(Address::class, 'addressable');
    }
}
