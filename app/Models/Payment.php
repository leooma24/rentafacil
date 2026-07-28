<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Payment extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['amount', 'payment_method', 'status', 'payment_date'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Pago {$eventName}");
    }

    protected $fillable = [
        'company_id',
        'rental_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference',
        'status',
        'applied',
    ];

    protected $casts = [
        'applied' => 'boolean',
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
