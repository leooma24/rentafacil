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
    ) {
    }
}
