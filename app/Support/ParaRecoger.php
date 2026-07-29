<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Rental;
use Illuminate\Support\Collection;

/**
 * A quién ya toca irle a quitar el equipo.
 *
 * El sistema trataba igual al que se pasó tres días y al que lleva un mes: los
 * dos caían en "Avisos de hoy" con el mismo recordatorio de WhatsApp. Pero no son
 * la misma conversación. Al de tres días se le avisa; al del mes se va por la
 * lavadora, porque cada semana que sigue allá es una semana que ese aparato no
 * está generando con alguien que sí paga.
 *
 * El corte lo pone cada rentador en sus Preferencias. Arranca en dos periodos,
 * que es lo que hace el negocio —se renta por semana y a la segunda que no cae el
 * pago se va por ella—, pero la tolerancia de cada quien es distinta y
 * equivocarse por el lado duro cuesta un cliente.
 *
 * A diferencia de los avisos, aquí NO se exige teléfono: para recoger no hace
 * falta poder mandarle un mensaje, hace falta saber dónde vive.
 */
class ParaRecoger
{
    /** @param Collection<int, Rental> $rentas */
    private function __construct(
        public readonly Collection $rentas,
        public readonly int $periodosDeTolerancia,
    ) {
    }

    public static function for(Company $empresa): self
    {
        $tolerancia = (int) ($empresa->settings->periodos_para_recoger ?? 0);

        if ($tolerancia <= 0) {
            return new self(collect(), 0);
        }

        $estados = app(AccountStatement::class);

        $rentas = $empresa->rentals()
            ->whereIn('status', ['activa', 'vencida'])
            ->whereDate('end_date', '<', now()->toDateString())
            // El equipo ya marcado como extraviado no entra: el cliente se mudó
            // con él y eso ya se sabe. Decirle "ve por esa lavadora" es ruido en
            // una lista cuyo valor es que todo lo que sale sí se puede ir a
            // recoger hoy.
            ->whereHas('washingMachine', fn ($query) => $query->where('status', '<>', 'extraviada'))
            ->with(['customer.addresses', 'washingMachine', 'payments'])
            ->orderBy('end_date')
            ->get()
            ->filter(fn (Rental $renta) => $estados->forRental($renta)->overduePeriods >= $tolerancia)
            ->values();

        return new self($rentas, $tolerancia);
    }

    public function hay(): bool
    {
        return $this->rentas->isNotEmpty();
    }

    public function cuantas(): int
    {
        return $this->rentas->count();
    }

    /**
     * Cuánto se está dejando de ganar cada semana con esos equipos allá.
     *
     * Es el número que decide: no es lo que te deben —eso ya está perdido— sino
     * lo que sigue costando cada semana que no vas por la lavadora.
     */
    public function rentaDetenidaPorPeriodo(): float
    {
        return (float) $this->rentas->sum(
            fn (Rental $renta) => RentalTerms::forRental($renta)->price ?? 0
        );
    }

    /** Cuántos de ésos están ubicados, para saber si se puede armar la ruta. */
    public function ubicados(): int
    {
        return $this->rentas
            ->filter(fn (Rental $renta) => $renta->customer?->addresses?->first()?->hasCoordinates())
            ->count();
    }
}
