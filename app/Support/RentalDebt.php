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
        /**
         * El recargo por atraso, ya incluido en $amount.
         *
         * Va aparte además de sumado para poder desglosarlo en el estado de
         * cuenta: un total que crece sin explicación es lo que hace que el
         * cliente desconfíe y llame a reclamar.
         */
        public readonly float $lateFee = 0.0,
    ) {
    }

    public function hasLateFee(): bool
    {
        return $this->lateFee > 0;
    }

    /** El adeudo sin el recargo, que es la renta pura. */
    public function rentOnly(): float
    {
        return max(0.0, $this->amount - $this->lateFee);
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
