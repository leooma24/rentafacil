<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'rental_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference',
        'status',
    ];

    /**
     * Get the rental that owns the payment.
     */
    public function rental()
    {
        return $this->belongsTo(Rental::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
