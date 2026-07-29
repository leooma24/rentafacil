<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    /** Cómo se calcula el recargo por atraso. */
    public const LATE_FEE_TYPES = [
        'fijo' => 'Una cantidad fija por periodo vencido',
        'porcentaje' => 'Un porcentaje de la renta vencida',
    ];

    protected $fillable = [
        'company_id',
        'price',
        'days_per_payment',
        'late_fee_amount',
        'late_fee_type',
        'late_fee_grace_days',
    ];

    /** Con el monto en cero no hay recargo y el adeudo se calcula como siempre. */
    public function chargesLateFee(): bool
    {
        return (float) $this->late_fee_amount > 0;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

}
