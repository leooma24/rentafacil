<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = ['street', 'city', 'number', 'interior_number', 'state_id', 'postal_code', 'township_id', 'country_id', 'neighborhood_id', 'latitude', 'longitude'];

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->street,
            $this->number ? "#{$this->number}" : null,
            $this->city,
            $this->postal_code ? "CP {$this->postal_code}" : null,
        ]);
        return implode(', ', $parts);
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }


    public function addressable()
    {
        return $this->morphTo();
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id',);
    }

    public function township()
    {
        return $this->belongsTo(Township::class, 'township_id');
    }

    public function neighborhood()
    {
        return $this->belongsTo(Neighborhood::class, 'neighborhood_id');
    }
}
