<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rental extends Model
{
    use HasFactory;

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
        return $this->due_date < now() && $this->status === 'activa';
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
