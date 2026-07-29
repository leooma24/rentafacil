<?php

namespace App\Support;

use App\Models\WashingMachine;

/**
 * Cuánto te ha dejado de verdad una lavadora.
 *
 * Hasta ahora la pantalla enseñaba una sola columna, "Ingresos Totales", que es
 * lo cobrado y nada más. Con eso una Samsung que cobró $8,000, costó $11,200 y
 * llevó $2,500 en reparaciones aparecía hasta arriba de la lista, como si fuera
 * la mejor del parque, cuando en realidad va perdiendo $5,700.
 *
 * Y con ese número se toma la decisión de qué marca volver a comprar. Un número
 * equivocado ahí es peor que no tener ninguno: no deja pensar "no sé", deja
 * pensar mal con confianza.
 *
 * El mantenimiento entra porque en este negocio es el gasto que decide. Un
 * aparato barato que se descompone cada dos meses sale más caro que uno del doble
 * de precio que no se descompone nunca, y eso sólo se ve sumando las órdenes.
 */
class RentabilidadDelEquipo
{
    private function __construct(
        public readonly WashingMachine $equipo,
        /** Lo cobrado a los clientes que la han tenido. */
        public readonly float $cobrado,
        public readonly float $compra,
        public readonly float $mantenimiento,
        public readonly int $rentas,
    ) {
    }

    public static function for(WashingMachine $equipo): self
    {
        return new self(
            equipo: $equipo,
            cobrado: (float) $equipo->rentals()
                ->join('payments', 'payments.rental_id', '=', 'rentals.id')
                ->where('payments.status', 'completado')
                ->sum('payments.amount'),
            compra: (float) ($equipo->purchase_price ?? 0),
            mantenimiento: (float) $equipo->maintenances()->sum('cost'),
            rentas: $equipo->rentals()->count(),
        );
    }

    /** Lo que llevas gastado en ella: lo que costó más lo que ha costado tener. */
    public function gastado(): float
    {
        return $this->compra + $this->mantenimiento;
    }

    /** Lo que de verdad te ha dejado. Puede ser negativo, y eso es el punto. */
    public function resultado(): float
    {
        return $this->cobrado - $this->gastado();
    }

    public function yaSePago(): bool
    {
        return $this->gastado() > 0 && $this->resultado() >= 0;
    }

    /**
     * Sin precio de compra no se puede decir nada.
     *
     * Se distingue de "no ha dejado nada" a propósito: el cero de un dato que
     * falta y el cero de un aparato que no genera se ven igual en la pantalla y
     * significan cosas opuestas.
     */
    public function calculable(): bool
    {
        return $this->compra > 0;
    }

    public function faltaPorRecuperar(): float
    {
        return max(0, $this->gastado() - $this->cobrado);
    }

    /**
     * En cuántos periodos se termina de pagar, al ritmo que lleva.
     *
     * Al ritmo real y no al precio de lista: si esa lavadora pasa la mitad del
     * año en la bodega, el dato que sirve es el que incluye ese tiempo muerto.
     */
    public function periodosParaPagarse(): ?int
    {
        if (! $this->calculable() || $this->yaSePago()) {
            return null;
        }

        $terms = RentalTerms::for($this->equipo->company);
        $precio = (float) ($terms->price ?? 0);

        if ($precio <= 0) {
            return null;
        }

        return (int) ceil($this->faltaPorRecuperar() / $precio);
    }

    /** El veredicto en una frase, que es lo que se lee de verdad. */
    public function veredicto(): string
    {
        if (! $this->calculable()) {
            return 'Falta su precio de compra';
        }

        if ($this->yaSePago()) {
            return 'Ya se pagó · te deja $' . number_format($this->resultado(), 2);
        }

        $periodos = $this->periodosParaPagarse();

        return $periodos !== null
            ? 'Le faltan ' . $periodos . ' cobros para pagarse'
            : 'Le faltan $' . number_format($this->faltaPorRecuperar(), 2);
    }

    public function color(): string
    {
        if (! $this->calculable()) {
            return 'gray';
        }

        if ($this->yaSePago()) {
            return 'success';
        }

        // Ni la mitad recuperada y ya lleva mantenimiento encima: ésa es la que
        // hay que mirar de cerca.
        return $this->cobrado < $this->gastado() / 2 ? 'danger' : 'warning';
    }
}
