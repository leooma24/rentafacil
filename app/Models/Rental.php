<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Rental extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'start_date', 'end_date', 'customer_id', 'washing_machine_id', 'price', 'deposit'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Renta {$eventName}");
    }

    protected $fillable = [
        'company_id', 'customer_id', 'washing_machine_id', 'start_date', 'end_date',
        'status', 'notes', 'price', 'deposit', 'deposit_returned', 'deposit_returned_at',
        'delivered_at', 'delivery_notes', 'delivery_photos', 'pickup_photos',
    ];

    protected $casts = [
        'deposit_returned_at' => 'datetime',
        'delivered_at' => 'datetime',
        'delivery_photos' => 'array',
        'pickup_photos' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function washingMachine(): BelongsTo
    {
        return $this->belongsTo(WashingMachine::class);
    }

    public function isOverdue(): bool
    {
        return $this->end_date < now() && $this->status === 'activa';
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /** Trae depósito y todavía no se le devuelve. */
    public function hasPendingDeposit(): bool
    {
        return (float) $this->deposit > 0 && $this->deposit_returned_at === null;
    }

    public function isDelivered(): bool
    {
        return $this->delivered_at !== null;
    }

    /**
     * Falta registrar la entrega.
     *
     * Sólo aplica a rentas vivas: a las 160 que ya existen no se les va a pedir
     * una entrega que ocurrió antes de que la app supiera registrarlas.
     */
    public function needsDelivery(): bool
    {
        return ! $this->isDelivered() && in_array($this->status, ['activa', 'vencida'], true);
    }
}
