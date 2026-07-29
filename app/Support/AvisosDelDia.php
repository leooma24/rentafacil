<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Rental;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * A quién hay que avisarle hoy que se le vence o que ya se le venció.
 *
 * El botón de WhatsApp existe desde hace tiempo, pero hay que ir cliente por
 * cliente buscándolos en la lista. Con treinta clientes eso son treinta
 * búsquedas, y por eso no se hace.
 *
 * No se manda solo: WhatsApp automático necesita la API de pago, y el dueño ya
 * dijo que no quiere gastos operativos nuevos. Esto arma la cola y el mensaje;
 * el envío sigue siendo un toque.
 */
class AvisosDelDia
{
    /** Con cuántos días de anticipación se avisa de un vencimiento. */
    public const DIAS_DE_ANTICIPACION = 3;

    /** @param Collection<int, Aviso> $avisos */
    private function __construct(public readonly Collection $avisos)
    {
    }

    public static function for(Company $empresa): self
    {
        $hoy = Carbon::today();

        $rentas = $empresa->rentals()
            ->whereIn('status', ['activa', 'vencida'])
            ->whereDate('end_date', '<=', $hoy->copy()->addDays(self::DIAS_DE_ANTICIPACION))
            ->with(['customer', 'washingMachine'])
            ->orderBy('end_date')
            ->get()
            // Sin teléfono no hay a dónde mandar el aviso.
            ->filter(fn (Rental $renta) => filled($renta->customer?->phone));

        $estados = app(AccountStatement::class);

        return new self($rentas->map(function (Rental $renta) use ($estados, $hoy) {
            $vence = Carbon::parse($renta->end_date)->startOfDay();
            $vencida = $vence->lt($hoy);

            return new Aviso(
                rental: $renta,
                vencida: $vencida,
                dias: (int) $vence->diffInDays($hoy, false) * ($vencida ? 1 : -1),
                adeudo: $vencida ? $estados->forRental($renta)->amount : 0.0,
            );
        })->values());
    }

    /** Lo ya vencido primero: es lo que urge. */
    public function porUrgencia(): Collection
    {
        return $this->avisos->sortByDesc(fn (Aviso $aviso) => $aviso->vencida ? $aviso->dias + 1000 : -$aviso->dias)
            ->values();
    }

    public function vencidos(): Collection
    {
        return $this->avisos->filter(fn (Aviso $aviso) => $aviso->vencida)->values();
    }

    public function porVencer(): Collection
    {
        return $this->avisos->reject(fn (Aviso $aviso) => $aviso->vencida)->values();
    }

    public function hayAvisos(): bool
    {
        return $this->avisos->isNotEmpty();
    }
}
