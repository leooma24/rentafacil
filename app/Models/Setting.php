<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'price',
        'days_per_payment',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

}
