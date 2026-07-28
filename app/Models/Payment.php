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
        'collected_by',
    ];

    protected $casts = [
        'applied' => 'boolean',
    ];

    /**
     * Quién registró el cobro se anota solo.
     *
     * Va aquí y no en el observador porque tiene que estar puesto antes de
     * guardar: el corte de caja se arma con esta columna y un cobro sin dueño
     * no se le puede cargar a nadie.
     */
    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            $payment->collected_by ??= auth()->id();
        });
    }

    /** Quien lo cobró. Nulo en los cobros anteriores a que se registrara. */
    public function collector()
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

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
