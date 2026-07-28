<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * El corte de caja de un día, ya firmado.
 *
 * Guarda lo esperado además de lo contado aunque lo esperado se pueda
 * recalcular: si mañana se corrige un cobro viejo, el corte que ya se cerró
 * tiene que seguir diciendo lo que decía ese día.
 */
class CashClosing extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'closing_date',
        'expected_cash',
        'counted_cash',
        'difference',
        'payments_count',
        'notes',
    ];

    protected $casts = [
        'closing_date' => 'date',
        'expected_cash' => 'decimal:2',
        'counted_cash' => 'decimal:2',
        'difference' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cuadra(): bool
    {
        return (float) $this->difference === 0.0;
    }

    public function falta(): bool
    {
        return (float) $this->difference < 0;
    }
}
