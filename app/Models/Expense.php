<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un gasto del negocio.
 *
 * Las categorías están fijas y no en una tabla aparte: son las mismas para
 * cualquier rentador de lavadoras y dejarlas abiertas terminaría en "gasolina",
 * "Gasolina" y "gas" contando por separado.
 */
class Expense extends Model
{
    use SoftDeletes;

    public const CATEGORIAS = [
        'gasolina' => 'Gasolina y casetas',
        'sueldos' => 'Sueldos y comisiones',
        'refacciones' => 'Refacciones y materiales',
        'compra' => 'Compra de lavadoras',
        'local' => 'Renta del local',
        'servicios' => 'Luz, agua, teléfono',
        'transporte' => 'Fletes y mudanzas',
        'otros' => 'Otros',
    ];

    protected $fillable = [
        'company_id',
        'user_id',
        'category',
        'description',
        'amount',
        'expense_date',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /** Quién lo registró se anota solo, igual que en los cobros. */
    protected static function booted(): void
    {
        static::creating(function (Expense $gasto) {
            $gasto->user_id ??= auth()->id();
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function categoriaLegible(): string
    {
        return self::CATEGORIAS[$this->category] ?? $this->category;
    }
}
