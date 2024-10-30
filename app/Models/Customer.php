<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'phone', 'address'];

    public function company() : BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function rentals() : HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function washingMachines() : BelongsToMany
    {
        return $this->belongsToMany(WashingMachine::class, 'rentals')
                    ->withPivot('start_date', 'end_date', 'status', 'notes')
                    ->withTimestamps();
    }

    public function address() : HasOne
    {
        return $this->hasOne(Address::class);
    }

    public function addresses()
    {
        return $this->morphMany(Address::class, 'addressable');
    }
}
