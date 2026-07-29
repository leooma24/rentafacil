<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Un papel del cliente: INE, comprobante de domicilio o referencia.
 *
 * Es lo que permite recuperar un aparato cuando alguien se muda con él, y hasta
 * ahora no había dónde guardarlo.
 */
class CustomerDocument extends Model
{
    public const TYPES = [
        'ine' => 'INE / identificación',
        'comprobante' => 'Comprobante de domicilio',
        'referencia' => 'Referencia personal',
        'contrato' => 'Contrato firmado',
        'otro' => 'Otro',
    ];

    protected $fillable = [
        'customer_id',
        'uploaded_by',
        'type',
        'file_path',
        'original_name',
        'notes',
    ];

    /** Quién lo subió se anota solo, igual que en cobros y gastos. */
    protected static function booted(): void
    {
        static::creating(function (CustomerDocument $documento) {
            $documento->uploaded_by ??= auth()->id();
        });

        // El archivo se va con el registro: dejarlo huérfano en el disco es
        // guardar la identificación de alguien sin que nadie sepa que está ahí.
        //
        // Disco local y no público: estos papeles no se sirven por el navegador,
        // salen por una ruta que comprueba quién los pide.
        static::deleted(function (CustomerDocument $documento) {
            if ($documento->file_path) {
                Storage::disk('local')->delete($documento->file_path);
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
