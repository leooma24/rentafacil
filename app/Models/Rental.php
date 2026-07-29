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
    ];

    protected $casts = [
        'deposit_returned_at' => 'datetime',
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
}
