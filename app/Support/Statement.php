<?php

namespace App\Support;

use App\Models\Customer;
use Carbon\Carbon;

/**
 * Estado de cuenta de un cliente.
 *
 * `calculable` es false cuando la empresa no tiene precio o periodo configurado.
 * En ese caso el total es 0 pero NO significa que no deba: significa que no se
 * puede saber, y la interfaz debe decirlo así en vez de mostrar un cero falso.
 */
class Statement
{
    /** @param RentalDebt[] $lines */
    public function __construct(
        public readonly Customer $customer,
        public readonly float $total,
        public readonly ?Carbon $owingSince,
        public readonly array $lines,
        public readonly bool $calculable,
    ) {
    }

    public function hasDebt(): bool
    {
        return $this->calculable && $this->total > 0;
    }

    /**
     * Lo que el cliente dejó en garantía y todavía no se le devuelve.
     *
     * Va aparte del total y nunca se le resta: es dinero del cliente en poder del
     * dueño, no un abono a su deuda. Mezclarlos haría creer que debe menos.
     */
    public function depositosEnGarantia(): float
    {
        return collect($this->lines)
            ->filter(fn (RentalDebt $linea) => $linea->rental->hasPendingDeposit())
            ->sum(fn (RentalDebt $linea) => (float) $linea->rental->deposit);
    }
}
