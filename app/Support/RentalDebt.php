<?php

namespace App\Support;

use App\Models\Rental;

/**
 * Lo que debe una renta en particular.
 */
class RentalDebt
{
    public function __construct(
        public readonly Rental $rental,
        public readonly int $overduePeriods,
        public readonly float $price,
        public readonly float $amount,
        /** Lo que ya abonó y todavía no compra tiempo. */
        public readonly float $credit = 0.0,
    ) {
    }

    public function hasCredit(): bool
    {
        return $this->credit > 0;
    }

    /** Lo que le falta para completar el periodo que está abonando. */
    public function missingForNextPeriod(): float
    {
        return max(0.0, $this->price - $this->credit);
    }
}
