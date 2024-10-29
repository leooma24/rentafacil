<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $fillable = ['nombre'];

    protected $table = 'paises';

    public function address() {
        return $this->belongsTo(Address::class);
    }

    public function states() {
        return $this->hasMany(State::class, 'pais_id');
    }
}
