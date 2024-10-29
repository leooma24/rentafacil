<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = ['street', 'city', 'number', 'interior_number', 'state_id', 'postal_code', 'township_id', 'country_id', 'customer_id', 'neighborhood_id'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
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
