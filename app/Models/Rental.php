<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Rental extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'start_date', 'end_date', 'customer_id', 'washing_machine_id'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Renta {$eventName}");
    }

    protected $fillable = ['company_id', 'customer_id', 'washing_machine_id', 'start_date', 'end_date', 'status', 'notes'];

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
}
