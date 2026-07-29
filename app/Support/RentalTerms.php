<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Rental;
use Carbon\Carbon;

/**
 * Las condiciones de renta vigentes de una empresa: cuánto cobra, cada cuántos
 * días y hasta cuándo queda cubierta una renta al pagar.
 *
 * Existe para que cobrar, entregar y recoger no repitan cada una la lectura de
 * Setting y la suma de días.
 */
class RentalTerms
{
    /** Lo que se usa cuando la empresa todavía no configura su periodo. */
    public const DIAS_POR_OMISION = 15;

    private function __construct(
        public readonly ?float $price,
        public readonly int $days,
    ) {
    }

    public static function for(Company $company): self
    {
        $settings = $company->settings;

        $precio = $settings && $settings->price > 0 ? (float) $settings->price : null;
        $dias = $settings && $settings->days_per_payment > 0
            ? (int) $settings->days_per_payment
            : self::DIAS_POR_OMISION;

        return new self($precio, $dias);
    }

    /**
     * Las condiciones de UNA renta: manda su precio propio y sólo si no trae uno
     * se usa el de la empresa.
     *
     * Así el dueño puede cobrar distinto por equipo o por cliente sin que cambiarle
     * el precio a la empresa le mueva lo que ya tiene rentado.
     */
    public static function forRental(Rental $renta): self
    {
        $empresa = self::for($renta->company ?? Company::find($renta->company_id));

        return $renta->price > 0
            ? new self((float) $renta->price, $empresa->days)
            : $empresa;
    }

    /** Sin precio no se puede cobrar; la acción avisa y liga a Preferencias. */
    public function isConfigured(): bool
    {
        return $this->price !== null;
    }

    /** La fecha hasta la que queda cubierta una renta que empieza en $desde. */
    public function endDateFrom(Carbon|string|null $desde = null): Carbon
    {
        $inicio = $desde ? Carbon::parse($desde) : Carbon::today();

        return $inicio->copy()->addDays($this->days);
    }

    /** El resumen que se le enseña al dueño antes de confirmar un cobro. */
    public function summary(): string
    {
        if (! $this->isConfigured()) {
            return 'Falta configurar tu precio de renta.';
        }

        return '$' . number_format($this->price, 2) . ' · ' . $this->days . ' días';
    }
}
