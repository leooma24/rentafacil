<?php

namespace App\Support;

use App\Models\Company;
use App\Models\ProspectiveClient;

/**
 * ¿Me alcanza para otra lavadora?
 *
 * Es la única decisión grande que toma un rentador —meter otro aparato— y era la
 * que menos apoyo tenía. Todas las gráficas del escritorio miran hacia atrás:
 * cuánto entró, cuánto se gastó, cómo va el mes. Ninguna contesta la pregunta que
 * se hace parado en la tienda de electrodomésticos.
 *
 * Y la respuesta no es una sola cifra, son tres que se leen juntas:
 *
 * 1. ¿Está todo colocado? Comprar con aparatos parados en la bodega es cambiar
 *    dinero por más dinero parado.
 * 2. ¿Hay a quién dárselo? Ocupación llena sin nadie esperando no es demanda,
 *    es coincidencia.
 * 3. ¿En cuánto se paga? Con la tarifa propia y no con una regla general.
 *
 * El veredicto se arma en ese orden a propósito: es el orden en que un rentador
 * con experiencia se hace las preguntas.
 */
class DecisionDeCrecer
{
    /** De aquí para arriba la ocupación ya es señal de que falta equipo. */
    private const OCUPACION_QUE_APRIETA = 85;

    /** Los estados que sí son parque activo: lo que se tiene y puede trabajar. */
    private const EN_JUEGO = ['disponible', 'rentada', 'en_revision', 'mantenimiento'];

    private function __construct(
        public readonly Company $empresa,
        public readonly int $parque,
        public readonly int $colocados,
        public readonly int $parados,
        public readonly int $prospectos,
        /** Lo que entra por periodo si todos los rentados pagan. */
        public readonly float $ingresoPorPeriodo,
        /** Lo que costó en promedio una lavadora, según lo que ya compró. */
        public readonly ?float $costoTipico,
        public readonly int $diasDelPeriodo,
    ) {
    }

    public static function for(Company $empresa): self
    {
        $equipos = $empresa->washingMachines()
            ->whereIn('status', self::EN_JUEGO)
            ->get();

        $activas = $empresa->rentals()
            ->whereIn('status', ['activa', 'vencida'])
            ->get();

        // El promedio se saca sólo de los que tienen precio: meter los que no lo
        // traen como cero bajaría el costo típico y haría ver la compra más
        // barata de lo que es.
        $conPrecio = $empresa->washingMachines()
            ->whereNotNull('purchase_price')
            ->where('purchase_price', '>', 0)
            ->pluck('purchase_price');

        return new self(
            empresa: $empresa,
            parque: $equipos->count(),
            colocados: $equipos->where('status', 'rentada')->count(),
            parados: $equipos->whereIn('status', ['disponible', 'en_revision'])->count(),
            prospectos: ProspectiveClient::whereNull('converted_user_id')->count(),
            ingresoPorPeriodo: (float) $activas->sum(
                fn ($renta) => RentalTerms::forRental($renta)->price ?? 0
            ),
            costoTipico: $conPrecio->isEmpty() ? null : round((float) $conPrecio->avg(), -1),
            diasDelPeriodo: (int) ($empresa->settings->days_per_payment ?? 0),
        );
    }

    public function ocupacion(): int
    {
        return $this->parque > 0
            ? (int) round($this->colocados / $this->parque * 100)
            : 0;
    }

    public function ocupacionAprieta(): bool
    {
        return $this->parque > 0 && $this->ocupacion() >= self::OCUPACION_QUE_APRIETA;
    }

    /** En cuántos cobros se paga una lavadora nueva, con la tarifa de la casa. */
    public function cobrosParaPagarse(): ?int
    {
        $precio = (float) ($this->empresa->settings->price ?? 0);

        if ($this->costoTipico === null || $precio <= 0) {
            return null;
        }

        return (int) ceil($this->costoTipico / $precio);
    }

    /** Y eso en semanas o meses, que es como se piensa el plazo. */
    public function tiempoParaPagarse(): ?string
    {
        $cobros = $this->cobrosParaPagarse();

        if ($cobros === null || $this->diasDelPeriodo <= 0) {
            return null;
        }

        $dias = $cobros * $this->diasDelPeriodo;

        if ($dias < 60) {
            return $dias . ' días';
        }

        $meses = (int) round($dias / 30);

        return $meses . ' meses';
    }

    /**
     * Qué conviene hacer, en una frase.
     *
     * Con el "no" primero: lo más común es que haya equipo parado, y entonces
     * comprar es la peor decisión posible aunque el dinero alcance.
     */
    public function veredicto(): string
    {
        if ($this->parque === 0) {
            return 'Todavía no has dado de alta ningún equipo.';
        }

        if ($this->parados > 0) {
            $cuantos = $this->parados === 1
                ? 'Tienes 1 equipo parado'
                : "Tienes {$this->parados} equipos parados";

            return $cuantos . ': colocar ésos te deja el mismo dinero que comprar, sin gastar nada.';
        }

        if (! $this->ocupacionAprieta()) {
            return 'Vas al ' . $this->ocupacion() . '% de ocupación. Todavía hay lugar sin comprar.';
        }

        if ($this->prospectos === 0) {
            return 'Todo tu equipo está colocado, pero no tienes a nadie esperando. '
                . 'Consigue la demanda antes de comprar.';
        }

        $plazo = $this->tiempoParaPagarse();

        $frase = 'Todo tu equipo está colocado y tienes ' . $this->prospectos
            . ($this->prospectos === 1 ? ' prospecto' : ' prospectos') . ' sin atender.';

        return $plazo !== null
            ? $frase . ' Una lavadora más se pagaría en ' . $plazo . '.'
            : $frase . ' Captura el precio de compra de tus equipos para saber en cuánto se paga una nueva.';
    }

    public function color(): string
    {
        return match (true) {
            $this->parque === 0 => 'gray',
            $this->parados > 0 => 'warning',
            $this->ocupacionAprieta() && $this->prospectos > 0 => 'success',
            default => 'info',
        };
    }

    /** Conviene comprar: todo colocado y con gente esperando. */
    public function convieneComprar(): bool
    {
        return $this->parados === 0
            && $this->ocupacionAprieta()
            && $this->prospectos > 0;
    }
}
