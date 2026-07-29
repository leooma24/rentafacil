<?php

namespace App\Support;

use App\Models\Rental;
use App\Models\RentalMachineChange;
use App\Models\WashingMachine;
use Illuminate\Support\Facades\DB;

/**
 * Cambiarle el equipo a un cliente sin tocarle la renta.
 *
 * Si una lavadora se descompone y se le lleva otra, hasta ahora había que
 * cancelar la renta y crear otra: se perdían los pagos y el saldo del cliente
 * arrancaba de cero, cuando no debería moverse ni un peso.
 *
 * Todo va en una transacción: si algo falla a media operación, no queremos que
 * el equipo viejo quede libre con el cliente todavía usándolo.
 */
class CambioDeEquipo
{
    /** A dónde va el equipo que se retira, según por qué se cambió. */
    private const DESTINO = [
        'falla' => 'mantenimiento',
        'mantenimiento' => 'mantenimiento',
        'peticion' => 'disponible',
        'mejora' => 'disponible',
        'otro' => 'disponible',
    ];

    public function ejecutar(
        Rental $renta,
        WashingMachine $nuevo,
        string $motivo,
        ?string $notas = null,
    ): RentalMachineChange {
        $anterior = $renta->washingMachine;

        if (! $anterior) {
            throw new \RuntimeException('La renta no trae equipo asignado.');
        }

        if ($anterior->is($nuevo)) {
            throw new \RuntimeException('Es el mismo equipo que ya tiene.');
        }

        if ($nuevo->status !== 'disponible') {
            throw new \RuntimeException('Ese equipo no está disponible.');
        }

        return DB::transaction(function () use ($renta, $anterior, $nuevo, $motivo, $notas) {
            $cambio = RentalMachineChange::create([
                'rental_id' => $renta->id,
                'from_machine_id' => $anterior->id,
                'to_machine_id' => $nuevo->id,
                'reason' => $motivo,
                'notes' => $notas,
            ]);

            $anterior->update(['status' => self::DESTINO[$motivo] ?? 'disponible']);
            $nuevo->update(['status' => 'rentada']);

            // La renta conserva pagos, fechas, precio, depósito y saldo: para el
            // cliente no cambió nada más que el aparato que tiene enfrente.
            //
            // La entrega sí se vuelve a pedir: es otro equipo y hace falta la
            // foto de cómo se dejó éste.
            $renta->update([
                'washing_machine_id' => $nuevo->id,
                'delivered_at' => null,
                'delivery_photos' => null,
                'delivery_notes' => null,
            ]);

            return $cambio;
        });
    }

    /** Los equipos que se le pueden dar a cambio. */
    public function disponiblesPara(Rental $renta): \Illuminate\Support\Collection
    {
        return WashingMachine::where('company_id', $renta->company_id)
            ->where('status', 'disponible')
            ->orderBy('machine_code')
            ->get();
    }
}
