<?php

namespace App\Support;

use App\Models\Customer;

/**
 * Cuánto depósito conviene pedirle a este cliente.
 *
 * El depósito es lo único que protege el aparato, y hoy es un campo libre que
 * casi nadie llena: se queda en cero porque llenarlo obliga a decidir un monto
 * parado en la puerta del cliente, con la lavadora ya en la camioneta.
 *
 * Aquí se propone uno, medido en periodos de renta y no en pesos sueltos, porque
 * así es como piensa el negocio: "dos semanas de garantía" se entiende y se
 * defiende enfrente del cliente; "$500" no se sabe de dónde salió.
 *
 * Es una sugerencia, nunca una imposición: el dueño conoce a su gente y hay
 * razones que no están en la base de datos. Lo que se evita es que el cero sea
 * la decisión por omisión.
 */
class DepositoSugerido
{
    /** Cuántos periodos de garantía según cómo se ha portado. */
    private const PERIODOS = [
        'fallo' => 3,
        'riesgo' => 2,
        'nuevo' => 2,
        'cumplido' => 0,
    ];

    private function __construct(
        public readonly float $monto,
        public readonly int $periodos,
        public readonly string $porque,
    ) {
    }

    public static function para(Customer $cliente, float $precioDeLaRenta): self
    {
        $historial = HistorialDelCliente::for($cliente);

        [$clave, $porque] = match (true) {
            $historial->yaFallo() => [
                'fallo',
                'Ya le recogiste un equipo quedando a deber.',
            ],
            // Se separan aunque pidan lo mismo: "se atrasa siempre" y "trae un
            // adeudo hoy" no son la misma cosa, y decirle al dueño la que no es
            // le hace perder la confianza en el resto de lo que dice la pantalla.
            $historial->pagaTarde() => [
                'riesgo',
                'Se atrasa en los pagos.',
            ],
            $historial->adeudoActual > 0 => [
                'riesgo',
                'Ahora mismo trae un adeudo contigo.',
            ],
            $historial->esNuevo() => [
                'nuevo',
                'Es cliente nuevo: todavía no sabes cómo paga.',
            ],
            default => [
                'cumplido',
                'Lleva ' . $historial->pagos . ' cobros pagando puntual: no hace falta pedirle.',
            ],
        };

        $periodos = self::PERIODOS[$clave];

        return new self(
            // Redondeado a decenas: un depósito de $487.50 no se cobra en la
            // puerta de nadie.
            monto: round($precioDeLaRenta * $periodos, -1),
            periodos: $periodos,
            porque: $porque,
        );
    }

    public function haceFalta(): bool
    {
        return $this->monto > 0;
    }

    /** La frase que se le pone al dueño debajo del campo. */
    public function ayuda(): string
    {
        if (! $this->haceFalta()) {
            return $this->porque;
        }

        return 'Te sugiero $' . number_format($this->monto, 2)
            . ' (' . $this->periodos . ' ' . ($this->periodos === 1 ? 'periodo' : 'periodos')
            . ' de renta). ' . $this->porque;
    }
}
